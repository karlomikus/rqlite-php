<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\ReadConsistencyLevel;

/*
|--------------------------------------------------------------------------
| Integration test
|--------------------------------------------------------------------------
|
| Runs against the three-node rqlite cluster defined in docker-compose.yml.
| Start the cluster first, then run:
|
|     docker compose run --rm php vendor/bin/pest --group=integration
|
| When running the test outside the PHP container, override the node URLs:
|
|     RQLITE_URL=http://localhost:4001 \
|     RQLITE_URL_2=http://localhost:4011 \
|     RQLITE_URL_3=http://localhost:4021 \
|     vendor/bin/pest --group=integration
|
*/

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
        ], withTransaction: true);

        expect($insert->results)->toHaveCount(2)
            ->and($insert->results[0]->lastInsertId)->toBeInt()
            ->and($insert->results[0]->rowsAffected)->toBe(1)
            ->and($insert->results[1]->lastInsertId)->toBeInt()
            ->and($insert->results[1]->rowsAffected)->toBe(1);

        $expectedRows = [
            ['id' => $insert->results[0]->lastInsertId, 'name' => 'Alpha', 'email' => $alphaEmail],
            ['id' => $insert->results[1]->lastInsertId, 'name' => 'Beta', 'email' => $betaEmail],
        ];

        foreach ($clients as $client) {
            $result = $client->query(
                [['SELECT id, name, email FROM users WHERE email IN (?, ?) ORDER BY id', $alphaEmail, $betaEmail]],
                readConsistencyLevel: ReadConsistencyLevel::Strong,
            );

            expect($result->results)->toHaveCount(1);
            expect($result->results[0]->rows)->toEqual($expectedRows);
        }
    } finally {
        if ($schemaLoaded) {
            $clients[0]->execute([
                ['DELETE FROM users WHERE email IN (?, ?)', $alphaEmail, $betaEmail],
            ]);
        }
    }
})->group('integration');
