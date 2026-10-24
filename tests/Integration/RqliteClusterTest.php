<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\ExecuteOptions;
use Kami\Rqlite\RequestOptions;
use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\ReadConsistencyLevel;
use Kami\Rqlite\Result\RequestResult;

it('loads the base schema and reads replicated rows from every node', function () {
    $clients = array_map(
        static fn (string $url): Rqlite => new Rqlite(new Curl($url)),
        [
            getenv('RQLITE_URL') ?: 'http://myrqlite-service-1:4001',
            getenv('RQLITE_URL_2') ?: 'http://myrqlite-service-2:4001',
            getenv('RQLITE_URL_3') ?: 'http://myrqlite-service-3:4001',
        ],
    );

    $marker = bin2hex(random_bytes(8));
    $alphaEmail = "alpha-{$marker}@example.com";
    $betaEmail = "beta-{$marker}@example.com";
    $schemaLoaded = false;

    try {
        $status = $clients[0]->status();
        expect($status['store']['ready'] ?? null)->toBeTrue();

        load_fixture($clients[0], 'schema.sql');
        $schemaLoaded = true;

        $insert = $clients[0]->execute([
            ['INSERT INTO users (name, email) VALUES (?, ?)', 'Alpha', $alphaEmail],
            ['INSERT INTO users (name, email) VALUES (?, ?)', 'Beta', $betaEmail],
        ], new ExecuteOptions(transaction: true));

        $insertResults = iterator_to_array($insert->results);

        expect($insertResults)->toHaveCount(2)
            ->and($insertResults[0]->lastInsertId)->toBeInt()
            ->and($insertResults[0]->rowsAffected)->toBe(1)
            ->and($insertResults[1]->lastInsertId)->toBeInt()
            ->and($insertResults[1]->rowsAffected)->toBe(1);

        $expectedRows = [
            ['id' => $insertResults[0]->lastInsertId, 'name' => 'Alpha', 'email' => $alphaEmail],
            ['id' => $insertResults[1]->lastInsertId, 'name' => 'Beta', 'email' => $betaEmail],
        ];

        foreach ($clients as $client) {
            $queryResults = $client->query(
                [['SELECT id, name, email FROM users WHERE email IN (?, ?) ORDER BY id', $alphaEmail, $betaEmail]],
                new QueryOptions(readConsistencyLevel: ReadConsistencyLevel::Strong),
            );

            $rows = [];
            $queryResult = null;

            foreach ($queryResults as $result) {
                $queryResult = $result;
                $rows = iterator_to_array($result->rows);
            }

            expect($queryResult)->not->toBeNull();
            expect($rows)->toEqual($expectedRows);
        }

        $requestResults = $clients[0]->request(
            [['SELECT id, name, email FROM users WHERE email IN (?, ?) ORDER BY id', $alphaEmail, $betaEmail]],
            new RequestOptions(readConsistencyLevel: ReadConsistencyLevel::Strong),
        );

        $requestResult = null;
        $requestRows = [];

        foreach ($requestResults as $result) {
            $requestResult = $result;
            $requestRows = iterator_to_array($result->rows);
        }

        expect($requestResult)->toBeInstanceOf(RequestResult::class);
        expect($requestRows)->toEqual($expectedRows);
    } finally {
        if ($schemaLoaded) {
            iterator_to_array($clients[0]->execute([
                ['DELETE FROM users WHERE email IN (?, ?)', $alphaEmail, $betaEmail],
            ])->results);
        }
    }
})->group('integration', 'cluster');
