<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Throwable;
use JsonException;
use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Exception\ClientException;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Exception\JsonDecodeException;
use Kami\Rqlite\Result\AssociativeQueryResult;

class Rqlite
{
    public function __construct(private readonly Adapter $httpAdapter)
    {
    }

    /**
     * Run a query via query endpoint
     *
     * @param array<mixed> $queries One or more queries
     * @param bool $timings Whether to include timings in the response
     * @return Results
     */
    public function query(array $queries, bool $timings = true, ?ReadConsistencyLevel $readConsistencyLevel = null): Results
    {
        try {
            $response = $this->httpAdapter->post('/db/query', $queries, [
                'timings' => $timings,
                'associative' => true,
                'level' => $readConsistencyLevel ? $readConsistencyLevel->value : null,
            ]);
        } catch (Throwable $e) {
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }

        $queryResults = [];
        $obj = $this->parseResponse($response);

        foreach ($obj['results'] as $result) {
            if ($result['error'] ?? null) {
                throw new RqliteException($result['error'], $queries);
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
     */
    public function execute(array $queries, bool $timings = false, ?int $timeoutInSeconds = null, bool $withTransaction = false): Results
    {
        try {
            $response = $this->httpAdapter->post('/db/execute', $queries, [
                'timings' => $timings,
                'associative' => true,
                'db_timeout' => $timeoutInSeconds ? $timeoutInSeconds . 's' : null,
                'transaction' => $withTransaction ? true : null,
            ]);
        } catch (Throwable $e) {
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }

        $queryResults = [];

        $obj = $this->parseResponse($response);

        foreach ($obj['results'] as $result) {
            if ($result['error'] ?? null) {
                throw new RqliteException($result['error'], $queries);
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
     */
    public function status(): array
    {
        $response = $this->httpAdapter->get('/status');

        $obj = $this->parseResponse($response);

        return $obj;
    }

    /**
     * @return array<mixed>
     */
    private function parseResponse(string $response): array
    {
        try {
            return json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw (new JsonDecodeException('Unable to decode response from rqlite'))->setResponse($response);
        }
    }
}
