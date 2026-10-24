<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use JsonException;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\SQLiteError;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Exception\DecodingException;
use Kami\Rqlite\Exception\StatementException;
use Kami\Rqlite\Exception\TransportException;
use Kami\Rqlite\Result\AssociativeQueryResult;

class Rqlite
{
    public function __construct(private readonly HttpAdapter $httpAdapter)
    {
    }

    /**
     * Run a query via query endpoint
     *
     * @param array<mixed> $queries One or more queries
     * @param bool $timings Whether to include timings in the response
     * @return Results
     * @throws HttpException
     * @throws DecodingException
     * @throws StatementException
     * @throws TransportException
     */
    public function query(array $queries, bool $timings = true, ?ReadConsistencyLevel $readConsistencyLevel = null): Results
    {
        $obj = $this->requestJson('POST', '/db/query', [
            'timings' => $timings,
            'associative' => true,
            'level' => $readConsistencyLevel ? $readConsistencyLevel->value : null,
        ], $queries);

        $this->throwOnRequestError($obj);

        $queryResults = [];

        foreach ($this->results($obj) as $index => $result) {
            $this->throwOnStatementError($index, $result);

            $types = $result['types'] ?? null;
            $rows = $result['rows'] ?? null;

            if (!is_array($types) || !is_array($rows)) {
                throw new DecodingException('The rqlite query result is missing types or rows.');
            }

            $queryResults[] = new AssociativeQueryResult(
                types: $types,
                rows: $rows,
                time: self::floatOrNull($result['time'] ?? null),
            );
        }

        return new Results(
            results: $queryResults,
            time: self::floatOrNull($obj['time'] ?? null),
        );
    }

    /**
     * Run a query via execute endpoint
     *
     * @param array<mixed> $queries One or more queries
     * @param bool $timings Whether to include timings in the response
     * @param int|null $timeoutInSeconds Timeout in seconds
     * @return Results
     * @throws HttpException
     * @throws DecodingException
     * @throws StatementException
     * @throws TransportException
     */
    public function execute(array $queries, bool $timings = false, ?int $timeoutInSeconds = null, bool $withTransaction = false): Results
    {
        $obj = $this->requestJson('POST', '/db/execute', [
            'timings' => $timings,
            'associative' => true,
            'db_timeout' => $timeoutInSeconds ? $timeoutInSeconds . 's' : null,
            'transaction' => $withTransaction ? true : null,
        ], $queries);

        $this->throwOnRequestError($obj);

        $queryResults = [];

        foreach ($this->results($obj) as $index => $result) {
            $this->throwOnStatementError($index, $result);

            $rowsAffected = $result['rows_affected'] ?? null;

            $queryResults[] = new ExecuteResult(
                lastInsertId: $result['last_insert_id'] ?? null,
                rowsAffected: $rowsAffected === null ? null : (int) $rowsAffected,
                time: self::floatOrNull($result['time'] ?? null),
            );
        }

        return new Results(
            results: $queryResults,
            time: self::floatOrNull($obj['time'] ?? null),
        );
    }

    /**
     * Get the status of the rqlite server
     *
     * @return array<mixed>
     * @throws HttpException
     * @throws DecodingException
     * @throws TransportException
     */
    public function status(): array
    {
        return $this->requestJson('GET', '/status');
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $uri
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null $json
     * @return array<mixed>
     * @throws HttpException
     * @throws DecodingException
     * @throws TransportException
     */
    private function requestJson(string $method, string $uri, array $query = [], ?array $json = null): array
    {
        $response = $this->httpAdapter->send(new Request($method, $uri, $query, json: $json));

        if (!$response->isSuccessful()) {
            throw new HttpException($response);
        }

        return $this->decodeResponse($response);
    }

    /**
     * Throw when rqlite reports an error for the whole request.
     *
     * @param array<mixed> $body
     * @throws RqliteException
     */
    private function throwOnRequestError(array $body): void
    {
        $error = $body['error'] ?? null;

        if (is_string($error) && $error !== '') {
            throw new RqliteException($error);
        }
    }

    /**
     * Return the decoded results, validating the response shape.
     *
     * @param array<mixed> $body
     * @return list<array<mixed>>
     * @throws DecodingException
     */
    private function results(array $body): array
    {
        $results = $body['results'] ?? null;

        if (!is_array($results) || !array_is_list($results)) {
            throw new DecodingException('The rqlite response does not contain a results array.');
        }

        $normalized = [];

        foreach ($results as $result) {
            if (!is_array($result)) {
                throw new DecodingException('Unexpected result in the rqlite response.');
            }

            $normalized[] = $result;
        }

        return $normalized;
    }

    /**
     * Throw when the decoded result for one statement contains an error.
     *
     * rqlite v10.6.0 and later render `error_v2`, optionally including SQLite
     * result codes. Older versions, or requests using `errors=v1`, render only
     * the legacy `error` string.
     *
     * @param array<mixed> $result
     * @throws StatementException
     */
    private function throwOnStatementError(int $index, array $result): void
    {
        $structured = $result['error_v2'] ?? null;

        if (is_array($structured)) {
            $message = $structured['message'] ?? null;

            if (!is_string($message) || $message === '') {
                $message = $this->legacyErrorMessage($result) ?? 'Unknown rqlite error';
            }

            throw new StatementException(
                $index,
                $message,
                $this->sqliteError($structured),
            );
        }

        $legacyMessage = $this->legacyErrorMessage($result);

        if ($legacyMessage !== null) {
            throw new StatementException($index, $legacyMessage);
        }
    }

    /**
     * @param array<mixed> $result
     */
    private function legacyErrorMessage(array $result): ?string
    {
        $error = $result['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
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

    /**
     * @return array<mixed>
     * @throws DecodingException
     */
    private function decodeResponse(Response $response): array
    {
        try {
            $decoded = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new DecodingException('Invalid JSON response from rqlite.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new DecodingException('Unexpected rqlite response.');
        }

        return $decoded;
    }
}
