<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Kami\Rqlite\Result\Results;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Result\AssociativeQueryResult;

final class Rqlite
{
    public function __construct(private readonly Adapter $httpAdapter)
    {
    }

    public function query(array $queries, bool $timings = true): Results
    {
        $response = $this->httpAdapter->post('/db/query', $queries, [
            'timings' => $timings,
            'associative' => true,
        ]);

        $queryResults = [];
        $obj = json_decode($response->getBody()->getContents(), true);
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

    public function execute(array $queries, bool $timings = false): Results
    {
        $response = $this->httpAdapter->post('/db/execute', $queries, [
            'timings' => $timings,
            'associative' => true,
        ]);

        $queryResults = [];
        $obj = json_decode($response->getBody()->getContents(), true);
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

    public function setConsistency(string $consistency)
    {
        
    }
}
