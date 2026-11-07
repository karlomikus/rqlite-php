<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use Kami\Rqlite\Http\Body;
use Kami\Rqlite\Http\JsonStream;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Result\AssociativeQueryResult;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\RequestResult;
use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Result\SQLiteError;
use Kami\Rqlite\Exception\DecodingException;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Exception\StatementException;
use Kami\Rqlite\Exception\TransportException;
use Psr\Http\Message\StreamInterface;

/**
 * @phpstan-type Statement (non-empty-string|array{0: non-empty-string, 1: array<string, scalar|null>}|array{0: non-empty-string, ...<int, scalar|null>})
 */
class Rqlite
{
    public function __construct(private readonly HttpAdapter $httpAdapter)
    {
    }

    /**
     * Run a query via the query endpoint.
     *
     * Both the results and each result's rows are streams. Consume a result's
     * rows before advancing to the next result.
     *
     * @param list<Statement> $queries One or more queries
     * @return Results<AssociativeQueryResult>
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws TransportException
     */
    public function query(array $queries, ?QueryOptions $options = null): Results
    {
        $options ??= new QueryOptions();

        $response = $this->sendRequest('POST', '/db/query', [
            'timings' => $options->timings,
            'pretty' => $options->pretty,
            'associative' => true,
            'blob_array' => $options->blobAsArray,
            'level' => $options->readConsistencyLevel?->value,
            'timeout' => $this->durationToString($options->timeout),
            'linearizable_timeout' => $this->durationToString($options->linearizableTimeout),
            'freshness' => $this->durationToString($options->freshness),
            'freshness_strict' => $options->freshnessStrict,
            'raft_index' => $options->raftIndex,
            'errors' => $options->errors?->value,
        ], $queries);

        /** @var Results<AssociativeQueryResult> $results */
        $results = new Results();
        $results->results = $this->streamQueryResponse($response->body, $results);

        return $results;
    }

    /**
     * Run one or more statements via the execute endpoint.
     *
     * @param list<Statement> $statements One or more statements
     * @return Results<ExecuteResult>
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws TransportException
     */
    public function execute(array $statements, ?ExecuteOptions $options = null): Results
    {
        $options ??= new ExecuteOptions();

        $response = $this->sendRequest('POST', '/db/execute', [
            'timings' => $options->timings,
            'pretty' => $options->pretty,
            'db_timeout' => $this->durationToString($options->dbTimeout),
            'timeout' => $this->durationToString($options->timeout),
            'transaction' => $options->transaction,
            'queue' => $options->queue,
            'wait' => $options->wait,
            'raft_index' => $options->raftIndex,
            'errors' => $options->errors?->value,
        ], $statements);

        /** @var Results<ExecuteResult> $results */
        $results = new Results();
        $results->results = $this->streamExecuteResponse($response->body, $results);

        return $results;
    }

    /**
     * Run one or more read or write statements via the unified request endpoint.
     *
     * @param list<Statement> $statements One or more statements
     * @return Results<RequestResult>
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws TransportException
     */
    public function request(array $statements, ?RequestOptions $options = null): Results
    {
        $options ??= new RequestOptions();

        $response = $this->sendRequest('POST', '/db/request', [
            'transaction' => $options->transaction,
            'timings' => $options->timings,
            'pretty' => $options->pretty,
            'associative' => true,
            'blob_array' => $options->blobAsArray,
            'level' => $options->readConsistencyLevel?->value,
            'timeout' => $this->durationToString($options->timeout),
            'linearizable_timeout' => $this->durationToString($options->linearizableTimeout),
            'freshness' => $this->durationToString($options->freshness),
            'freshness_strict' => $options->freshnessStrict,
            'raft_index' => $options->raftIndex,
            'errors' => $options->errors?->value,
        ], $statements);

        /** @var Results<RequestResult> $results */
        $results = new Results();
        $results->results = $this->streamRequestResponse($response->body, $results);

        return $results;
    }

    /**
     * Get the status of the rqlite server.
     *
     * @return array<mixed>
     * @throws HttpException
     * @throws DecodingException
     * @throws TransportException
     */
    public function status(): array
    {
        return $this->decodeDocument($this->sendRequest('GET', '/status')->body);
    }

    /**
     * Request a copy of the SQLite database from the node.
     *
     * The returned body is a stream implementing PSR-7's StreamInterface; the
     * caller is responsible for consuming and closing it.
     *
     * @throws HttpException
     * @throws TransportException
     */
    public function backup(?BackupOptions $options = null): Body
    {
        $options ??= new BackupOptions();

        return $this->sendRequest('GET', '/db/backup', [
            'fmt' => $options->format,
            'vacuum' => $options->vacuum,
            'compress' => $options->compress,
            'noleader' => $options->noLeader,
            'redirect' => $options->redirect,
        ])->body;
    }

    /**
     * Stream data into the node to load or restore data.
     *
     * The format of the data is detected automatically: data starting with
     * the SQLite file magic is sent as binary, anything else as plain SQL text.
     * A PSR-7 stream is consumed and detached; the caller must not use it
     * afterwards.
     *
     * @param resource|StreamInterface|string $data
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws TransportException
     */
    public function load($data, ?LoadOptions $options = null): void
    {
        $options ??= new LoadOptions();

        [$stream, $contentType] = $this->prepareUpload($data);

        $this->sendRawRequest('POST', '/db/load', [
            'redirect' => $options->redirect,
        ], $stream, $contentType);
    }

    /**
     * Stream a raw SQLite file into a single-node system, effectively
     * initializing the underlying SQLite database from scratch.
     *
     * It is an error to call this on anything but a single-node system.
     *
     * @param resource|StreamInterface|string $data
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws TransportException
     */
    public function boot($data): void
    {
        $stream = $this->toStream($data);

        $this->sendRawRequest('POST', '/boot', [], $stream, 'application/octet-stream');
    }

    /**
     * Send a request with a raw or stream body.
     *
     * @param non-empty-string $method
     * @param non-empty-string $uri
     * @param array<string, scalar|null> $query
     * @param resource|StreamInterface $stream
     * @throws HttpException
     * @throws TransportException
     */
    private function sendRawRequest(string $method, string $uri, array $query, $stream, string $contentType): Response
    {
        $response = $this->httpAdapter->send(new Request(
            $method,
            $uri,
            $query,
            ['Content-Type' => $contentType],
            stream: $stream,
        ));

        if (!$response->isSuccessful()) {
            throw new HttpException($response);
        }

        return $response;
    }

    /**
     * Prepare the upload payload.
     *
     * The first 13 bytes of the data are inspected to detect whether it is a
     * binary SQLite database file or plain SQL text. A non-seekable stream is
     * copied into a temporary buffer so its first bytes can be inspected.
     *
     * @param resource|StreamInterface|string $data
     * @return array{resource, string} The stream and the content type
     * @throws InvalidArgumentException
     */
    private function prepareUpload($data): array
    {
        $stream = $this->toStream($data);

        $prefix = fread($stream, 13);

        if ($prefix === 'SQLite format') {
            $contentType = 'application/octet-stream';
        } else {
            $contentType = 'text/plain';
        }

        if (stream_get_meta_data($stream)['seekable']) {
            rewind($stream);

            return [$stream, $contentType];
        }

        // The stream cannot be rewound, so copy it, together with the bytes
        // already read, into a temporary buffer.
        $buffer = fopen('php://temp', 'w+b');

        if ($buffer === false) {
            throw new RuntimeException('Unable to create a temporary upload stream.');
        }

        if ($prefix !== false && $prefix !== '') {
            fwrite($buffer, $prefix);
        }

        stream_copy_to_stream($stream, $buffer);
        rewind($buffer);

        return [$buffer, $contentType];
    }

    /**
     * Convert upload data into a readable stream.
     *
     * A PSR-7 stream is detached whenever possible so large files are not
     * copied; non-detachable streams are copied into a temporary buffer.
     *
     * @param resource|StreamInterface|string $data
     * @return resource
     * @throws InvalidArgumentException
     */
    private function toStream($data)
    {
        if (is_resource($data)) {
            return $data;
        }

        if ($data instanceof StreamInterface) {
            $resource = $data->detach();

            if (is_resource($resource)) {
                return $resource;
            }

            // The stream cannot be detached, so copy its remaining contents
            // into a temporary buffer.
            if ($data->isSeekable()) {
                $data->seek(0);
            }

            $stream = fopen('php://temp', 'w+b');

            if ($stream === false) {
                throw new RuntimeException('Unable to create an upload stream.');
            }

            fwrite($stream, $data->getContents());
            rewind($stream);

            return $stream;
        }

        if (is_string($data)) {
            $stream = fopen('php://temp', 'w+b');

            if ($stream === false) {
                throw new RuntimeException('Unable to create an upload stream.');
            }

            if ($data !== '') {
                fwrite($stream, $data);
                rewind($stream);
            }

            return $stream;
        }

        throw new InvalidArgumentException('The upload data must be a stream resource, a PSR-7 stream, or a string.');
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $uri
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null $json
     * @throws HttpException
     * @throws TransportException
     */
    private function sendRequest(string $method, string $uri, array $query = [], ?array $json = null): Response
    {
        $response = $this->httpAdapter->send(new Request($method, $uri, $query, json: $json));

        if (!$response->isSuccessful()) {
            throw new HttpException($response);
        }

        return $response;
    }

    /**
     * @return Generator<int, AssociativeQueryResult>
     * @throws DecodingException
     * @throws RqliteException
     * @throws StatementException
     */
    private function streamQueryResponse(Body $body, Results $results): Generator
    {
        $json = new JsonStream($body);
        $resultsSeen = false;

        $json->beginObject();

        while ($json->hasMore('}')) {
            $key = $json->readKey();

            switch ($key) {
                case 'results':
                    $resultsSeen = true;
                    $json->beginArray();

                    $index = 0;

                    while ($json->hasMore(']')) {
                        yield from $this->streamQueryResult($json, $index);
                        $json->separator(']');
                        $index++;
                    }

                    $json->endArray();
                    break;
                case 'error':
                    $this->throwOnRequestError($json->readValue());
                    break;
                case 'time':
                    $results->time = $this->floatOrNull($json->readValue());
                    break;
                default:
                    $json->skipValue();
                    break;
            }

            $json->separator('}');
        }

        $json->endObject();
        $json->ensureEnd();

        if (!$resultsSeen) {
            throw new DecodingException('The rqlite response does not contain a results array.');
        }
    }

    /**
     * @return Generator<int, AssociativeQueryResult>
     * @throws DecodingException
     * @throws StatementException
     */
    private function streamQueryResult(JsonStream $json, int $index): Generator
    {
        $json->beginObject();

        /** @var array<string, string> $types */
        $types = [];
        $typesSeen = false;
        $rowsSeen = false;
        $result = null;
        $time = null;
        $legacyError = null;
        $structuredError = null;

        while ($json->hasMore('}')) {
            $key = $json->readKey();

            switch ($key) {
                case 'types':
                    $value = $json->readValue();

                    if (!is_array($value)) {
                        throw new DecodingException('The rqlite query result has an invalid types field.');
                    }

                    /** @var array<string, string> $types */
                    $types = $value;
                    $typesSeen = true;

                    if ($result instanceof \Kami\Rqlite\Result\AssociativeQueryResult) {
                        $result->types = $types;
                    }
                    break;
                case 'rows':
                    $rowsSeen = true;
                    $rowsConsumed = false;
                    $rows = $this->streamRows($json, $rowsConsumed);
                    $result = new AssociativeQueryResult(types: $types, rows: $rows, time: $time);

                    yield $result;

                    // If the caller did not fully consume the rows, advance past
                    // them before parsing the rest of the result object.
                    if (!$rowsConsumed) {
                        foreach ($rows as $_) {
                        }
                    }
                    break;
                case 'time':
                    $time = $this->floatOrNull($json->readValue());

                    if ($result instanceof \Kami\Rqlite\Result\AssociativeQueryResult) {
                        $result->time = $time;
                    }
                    break;
                case 'error':
                    $value = $json->readValue();

                    if (is_string($value) && $value !== '') {
                        $legacyError = $value;
                    }
                    break;
                case 'error_v2':
                    $value = $json->readValue();

                    if (is_array($value)) {
                        $structuredError = $value;
                    }
                    break;
                default:
                    $json->skipValue();
                    break;
            }

            $json->separator('}');
        }

        $json->endObject();

        $this->throwOnStatementError($index, $legacyError, $structuredError);

        if (!$typesSeen || !$rowsSeen || !$result instanceof \Kami\Rqlite\Result\AssociativeQueryResult) {
            throw new DecodingException('The rqlite query result is missing types or rows.');
        }
    }

    /**
     * @param bool $completed Set to true once the row array has been consumed.
     * @return Generator<int, array<string, mixed>>
     * @throws DecodingException
     */
    private function streamRows(JsonStream $json, bool &$completed): Generator
    {
        $json->beginArray();

        while ($json->hasMore(']')) {
            $row = $json->readValue();

            if (!is_array($row)) {
                throw new DecodingException('The rqlite query result contains an invalid row.');
            }

            /** @var array<string, mixed> $row */
            yield $row;

            $json->separator(']');
        }

        $json->endArray();
        $completed = true;
    }

    /**
     * @return Generator<int, ExecuteResult>
     * @throws DecodingException
     * @throws RqliteException
     * @throws StatementException
     */
    private function streamExecuteResponse(Body $body, Results $results): Generator
    {
        $json = new JsonStream($body);
        $resultsSeen = false;

        $json->beginObject();

        while ($json->hasMore('}')) {
            $key = $json->readKey();

            switch ($key) {
                case 'results':
                    $resultsSeen = true;
                    $json->beginArray();

                    $index = 0;

                    while ($json->hasMore(']')) {
                        yield $this->streamExecuteResult($json, $index);
                        $json->separator(']');
                        $index++;
                    }

                    $json->endArray();
                    break;
                case 'error':
                    $this->throwOnRequestError($json->readValue());
                    break;
                case 'time':
                    $results->time = $this->floatOrNull($json->readValue());
                    break;
                default:
                    $json->skipValue();
                    break;
            }

            $json->separator('}');
        }

        $json->endObject();
        $json->ensureEnd();

        if (!$resultsSeen) {
            throw new DecodingException('The rqlite response does not contain a results array.');
        }
    }

    /**
     * @throws DecodingException
     * @throws StatementException
     */
    private function streamExecuteResult(JsonStream $json, int $index): ExecuteResult
    {
        $json->beginObject();

        $lastInsertId = null;
        $rowsAffected = null;
        $time = null;
        $legacyError = null;
        $structuredError = null;

        while ($json->hasMore('}')) {
            $key = $json->readKey();

            switch ($key) {
                case 'last_insert_id':
                    $lastInsertId = $json->readValue();
                    break;
                case 'rows_affected':
                    $rowsAffected = $json->readValue();
                    break;
                case 'time':
                    $time = $this->floatOrNull($json->readValue());
                    break;
                case 'error':
                    $value = $json->readValue();

                    if (is_string($value) && $value !== '') {
                        $legacyError = $value;
                    }
                    break;
                case 'error_v2':
                    $value = $json->readValue();

                    if (is_array($value)) {
                        $structuredError = $value;
                    }
                    break;
                default:
                    $json->skipValue();
                    break;
            }

            $json->separator('}');
        }

        $json->endObject();

        $this->throwOnStatementError($index, $legacyError, $structuredError);

        return new ExecuteResult(
            lastInsertId: $lastInsertId,
            rowsAffected: $rowsAffected === null ? null : (int) $rowsAffected,
            time: $time,
        );
    }

    /**
     * @return Generator<int, RequestResult>
     * @throws DecodingException
     * @throws RqliteException
     * @throws StatementException
     */
    private function streamRequestResponse(Body $body, Results $results): Generator
    {
        $json = new JsonStream($body);
        $resultsSeen = false;

        $json->beginObject();

        while ($json->hasMore('}')) {
            $key = $json->readKey();

            switch ($key) {
                case 'results':
                    $resultsSeen = true;
                    $json->beginArray();

                    $index = 0;

                    while ($json->hasMore(']')) {
                        yield from $this->streamRequestResult($json, $index);
                        $json->separator(']');
                        $index++;
                    }

                    $json->endArray();
                    break;
                case 'error':
                    $this->throwOnRequestError($json->readValue());
                    break;
                case 'time':
                    $results->time = $this->floatOrNull($json->readValue());
                    break;
                default:
                    $json->skipValue();
                    break;
            }

            $json->separator('}');
        }

        $json->endObject();
        $json->ensureEnd();

        if (!$resultsSeen) {
            throw new DecodingException('The rqlite response does not contain a results array.');
        }
    }

    /**
     * @return Generator<int, RequestResult>
     * @throws DecodingException
     * @throws StatementException
     */
    private function streamRequestResult(JsonStream $json, int $index): Generator
    {
        $json->beginObject();

        /** @var array<string, string> $types */
        $types = [];
        $typesSeen = false;
        $rowsSeen = false;
        $lastInsertId = null;
        $rowsAffected = null;
        $time = null;
        $legacyError = null;
        $structuredError = null;
        $result = null;

        while ($json->hasMore('}')) {
            $key = $json->readKey();

            switch ($key) {
                case 'types':
                    $value = $json->readValue();

                    if (!is_array($value)) {
                        throw new DecodingException('The rqlite request result has an invalid types field.');
                    }

                    /** @var array<string, string> $types */
                    $types = $value;
                    $typesSeen = true;

                    if ($result instanceof RequestResult) {
                        $result->types = $types;
                    }
                    break;
                case 'rows':
                    // rqlite renders `rows` as `null` for write statements when
                    // the request is made in associative form. Treat it as
                    // absent so the write result below is produced instead.
                    if ($json->nextIsNull()) {
                        $json->skipValue();
                        break;
                    }

                    $rowsSeen = true;
                    $rowsConsumed = false;
                    $rows = $this->streamRows($json, $rowsConsumed);
                    $result = new RequestResult(
                        types: $types,
                        rows: $rows,
                        lastInsertId: $lastInsertId,
                        rowsAffected: $rowsAffected === null ? null : (int) $rowsAffected,
                        time: $time,
                    );

                    yield $result;
                    break;
                case 'last_insert_id':
                    $lastInsertId = $json->readValue();

                    if ($result instanceof RequestResult) {
                        $result->lastInsertId = $lastInsertId;
                    }
                    break;
                case 'rows_affected':
                    $rowsAffected = $json->readValue();

                    if ($result instanceof RequestResult) {
                        $result->rowsAffected = $rowsAffected === null ? null : (int) $rowsAffected;
                    }
                    break;
                case 'time':
                    $time = $this->floatOrNull($json->readValue());

                    if ($result instanceof RequestResult) {
                        $result->time = $time;
                    }
                    break;
                case 'error':
                    $value = $json->readValue();

                    if (is_string($value) && $value !== '') {
                        $legacyError = $value;
                    }
                    break;
                case 'error_v2':
                    $value = $json->readValue();

                    if (is_array($value)) {
                        $structuredError = $value;
                    }
                    break;
                default:
                    $json->skipValue();
                    break;
            }

            $json->separator('}');
        }

        $json->endObject();

        $this->throwOnStatementError($index, $legacyError, $structuredError);

        if ($rowsSeen) {
            if (!$typesSeen) {
                throw new DecodingException('The rqlite request result is missing types or rows.');
            }

            return;
        }

        if ($lastInsertId === null && $rowsAffected === null) {
            throw new DecodingException('The rqlite request result is missing rows or execute fields.');
        }

        yield new RequestResult(
            types: $types,
            rows: $this->emptyRows(),
            lastInsertId: $lastInsertId,
            rowsAffected: $rowsAffected === null ? null : (int) $rowsAffected,
            time: $time,
        );
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function emptyRows(): Generator
    {
        yield from [];
    }

    /**
     * @return array<mixed>
     * @throws DecodingException
     */
    private function decodeDocument(Body $body): array
    {
        $json = new JsonStream($body);
        $decoded = $json->readValue();
        $json->ensureEnd();

        if (!is_array($decoded)) {
            throw new DecodingException('Unexpected rqlite response.');
        }

        return $decoded;
    }

    /**
     * @throws RqliteException
     */
    private function throwOnRequestError(mixed $error): void
    {
        if (is_string($error) && $error !== '') {
            throw new RqliteException($error);
        }
    }

    /**
     * Throw when a decoded result contains an error.
     *
     * rqlite v10.6.0 and later render `error_v2`, optionally including SQLite
     * result codes. Older versions, or requests using `errors=v1`, render only
     * the legacy `error` string.
     *
     * @param array<mixed>|null $structured
     * @throws StatementException
     */
    private function throwOnStatementError(int $index, ?string $legacyMessage, ?array $structured): void
    {
        if ($structured !== null) {
            $message = $structured['message'] ?? null;

            if (!is_string($message) || $message === '') {
                $message = $legacyMessage ?? 'Unknown rqlite error';
            }

            throw new StatementException(
                $index,
                $message,
                $this->sqliteError($structured),
            );
        }

        if ($legacyMessage !== null) {
            throw new StatementException($index, $legacyMessage);
        }
    }

    /**
     * @param array<mixed> $structured
     */
    private function sqliteError(array $structured): ?SQLiteError
    {
        $sqlite = $structured['sqlite'] ?? null;

        if (!is_array($sqlite)) {
            return null;
        }

        return new SQLiteError(
            code: (int) ($sqlite['code'] ?? 0),
            extendedCode: (int) ($sqlite['extended_code'] ?? 0),
            systemErrno: (int) ($sqlite['system_errno'] ?? 0),
        );
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function durationToString(int|float|string|null $duration): ?string
    {
        if ($duration === null) {
            return null;
        }

        if (is_string($duration)) {
            $duration = trim($duration);

            if ($duration === '') {
                throw new InvalidArgumentException('A duration option cannot be empty.');
            }

            return $duration;
        }

        if ($duration === 0 || $duration === 0.0) {
            return null;
        }

        return $duration . 's';
    }
}
