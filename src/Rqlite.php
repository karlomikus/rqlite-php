<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\AssociativeQueryResult;

final class Rqlite
{
    private ?ReadConsistencyLevel $readConsistencyLevel = null;

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
    public function query(array $queries, bool $timings = true): Results
    {
        $response = $this->httpAdapter->post('/db/query', $queries, [
            'timings' => $timings,
            'associative' => true,
            'level' => $this->readConsistencyLevel ? $this->readConsistencyLevel->value : false,
        ]);

        $queryResults = [];
        $obj = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        foreach ($obj['results'] as $result) {
            $queryResults[] = new AssociativeQueryResult(
                $result['types'],
                $result['rows'],
                $result['time'],
            );
        }

        return new Results(
            results: $queryResults,
            time: $obj['time'],
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
    public function execute(array $queries, bool $timings = false, ?int $timeoutInSeconds = null): Results
    {
        $response = $this->httpAdapter->post('/db/execute', $queries, [
            'timings' => $timings,
            'associative' => true,
            'db_timeout' => $timeoutInSeconds ? $timeoutInSeconds . 's' : null,
            'level' => $this->readConsistencyLevel ? $this->readConsistencyLevel->value : false,
        ]);

        $queryResults = [];
        $obj = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        foreach ($obj['results'] as $result) {
            $queryResults[] = new ExecuteResult(
                error: $result['error'] ?? null,
                lastInsertId: $result['last_insert_id'] ?? null,
                rowsAffected: $result['rows_affected'] ?? null,
                time: $result['time'] ?? null,
            );
        }

        return new Results(
            results: $queryResults,
            time: $obj['time'],
        );
    }

    public function setConsistency(?ReadConsistencyLevel $consistency): self
    {
        $this->readConsistencyLevel = $consistency;

        return $this;
    }
}
