<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Generator;
use Kami\Rqlite\Http\Body;
use Kami\Rqlite\Http\JsonStream;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Result\AssociativeQueryResult;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Result\SQLiteError;
use Kami\Rqlite\Exception\DecodingException;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Exception\StatementException;
use Kami\Rqlite\Exception\TransportException;

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
     * @param array<mixed> $queries One or more queries
     * @param bool $timings Whether to include timings in the response
     * @return Results
     * @throws HttpException
     * @throws TransportException
     */
    public function query(array $queries, bool $timings = true, ?ReadConsistencyLevel $readConsistencyLevel = null): Results
    {
        $response = $this->request('POST', '/db/query', [
            'timings' => $timings,
            'associative' => true,
            'level' => $readConsistencyLevel ? $readConsistencyLevel->value : null,
        ], $queries);

        $results = new Results();
        $results->results = $this->streamQueryResponse($response->body, $results);

        return $results;
    }

    /**
     * Run a query via the execute endpoint.
     *
     * @param array<mixed> $queries One or more queries
     * @param bool $timings Whether to include timings in the response
     * @param int|null $timeoutInSeconds Timeout in seconds
     * @return Results
     * @throws HttpException
     * @throws TransportException
     */
    public function execute(array $queries, bool $timings = false, ?int $timeoutInSeconds = null, bool $withTransaction = false): Results
    {
        $response = $this->request('POST', '/db/execute', [
            'timings' => $timings,
            'associative' => true,
            'db_timeout' => $timeoutInSeconds ? $timeoutInSeconds . 's' : null,
            'transaction' => $withTransaction ? true : null,
        ], $queries);

        $results = new Results();
        $results->results = $this->streamExecuteResponse($response->body, $results);

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
        return $this->decodeDocument($this->request('GET', '/status')->body);
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $uri
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null $json
     * @throws HttpException
     * @throws TransportException
     */
    private function request(string $method, string $uri, array $query = [], ?array $json = null): Response
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
                    $results->time = self::floatOrNull($json->readValue());
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

                    if ($result !== null) {
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
                    $time = self::floatOrNull($json->readValue());

                    if ($result !== null) {
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

        if (!$typesSeen || !$rowsSeen || $result === null) {
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
                    $results->time = self::floatOrNull($json->readValue());
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
                    $time = self::floatOrNull($json->readValue());
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

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
