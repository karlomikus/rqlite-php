<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use JsonException;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Exception\HttpException;
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

        $queryResults = [];

        foreach ($obj['results'] as $index => $result) {
            if ($result['error'] ?? null) {
                throw new StatementException($index, $result['error']);
            }

            $queryResults[] = new AssociativeQueryResult(
                types: $result['types'],
                rows: $result['rows'],
                time: $result['time'] ? (float) $result['time'] : null,
            );
        }

        return new Results(
            results: $queryResults,
            time: $obj['time'] ? (float) $obj['time'] : null,
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

        $queryResults = [];

        foreach ($obj['results'] as $index => $result) {
            if ($result['error'] ?? null) {
                throw new StatementException($index, $result['error']);
            }

            $queryResults[] = new ExecuteResult(
                lastInsertId: $result['last_insert_id'] ?? null,
                rowsAffected: $result['rows_affected'] ? (int) $result['rows_affected'] : null,
                time: $result['time'] ? (float) $result['time'] : null,
            );
        }

        return new Results(
            results: $queryResults,
            time: $obj['time'] ? (float) $obj['time'] : null,
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
     * @return array<mixed>
     * @throws DecodingException
     */
    private function decodeResponse(Response $response): array
    {
        try {
            $decoded = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DecodingException('Invalid JSON response from rqlite.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new DecodingException('Unexpected rqlite response.');
        }

        return $decoded;
    }
}
