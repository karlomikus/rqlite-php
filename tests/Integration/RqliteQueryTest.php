<?php

declare(strict_types=1);

use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Result\AssociativeQueryResult;

/*
|--------------------------------------------------------------------------
| Query integration test
|--------------------------------------------------------------------------
|
| Mirrors the scenarios covered by Test_Query in the Go HTTP client's
| http_test.go, run against the real cluster defined in docker-compose.yml.
| The PHP client always requests associative results, so assertions use the
| types/rows form rather than columns/values.
|
|     docker compose run --rm php vendor/bin/pest --group=integration
|
| When running the test outside the PHP container, override the node URL:
|
|     RQLITE_URL=http://localhost:4001 vendor/bin/pest --group=integration
|
*/

function query_test_seed(Rqlite $rqlite, string $table): void
{
    integration_execute($rqlite, [
        sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT)', $table),
        [sprintf('INSERT INTO %s (name) VALUES (?)', $table), 'Alice'],
        [sprintf('INSERT INTO %s (name) VALUES (?)', $table), 'Bob'],
    ]);
}

it('executes a simple SELECT query', function () {
    $rqlite = integration_client();
    $table = integration_table('query_simple');

    try {
        query_test_seed($rqlite, $table);

        $query = integration_query($rqlite, [
            sprintf('SELECT id, name FROM %s ORDER BY id', $table),
        ]);

        expect($query['items'])->toHaveCount(1)
            ->and($query['items'][0])->toBeInstanceOf(AssociativeQueryResult::class)
            ->and($query['items'][0]->types)->toEqual(['id' => 'integer', 'name' => 'text'])
            ->and($query['rows'][0])->toEqual([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');

it('executes a SELECT query with options', function () {
    $rqlite = integration_client();
    $table = integration_table('query_with_options');

    try {
        query_test_seed($rqlite, $table);

        $query = integration_query(
            $rqlite,
            [sprintf('SELECT id, name FROM %s ORDER BY id', $table)],
            new QueryOptions(pretty: true, timeout: '2s'),
        );

        expect($query['items'])->toHaveCount(1)
            ->and($query['items'][0])->toBeInstanceOf(AssociativeQueryResult::class)
            ->and($query['items'][0]->types)->toEqual(['id' => 'integer', 'name' => 'text'])
            ->and($query['rows'][0])->toEqual([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');

it('executes multiple SELECT queries in one request', function () {
    $rqlite = integration_client();

    $query = integration_query($rqlite, [
        'SELECT 1 AS value',
        'SELECT 2 AS value',
    ]);

    expect($query['items'])->toHaveCount(2)
        ->and($query['items'][0]->types)->toEqual(['value' => 'integer'])
        ->and($query['items'][1]->types)->toEqual(['value' => 'integer'])
        ->and($query['rows'][0])->toEqual([['value' => 1]])
        ->and($query['rows'][1])->toEqual([['value' => 2]]);
})->group('integration');

it('executes a SELECT query with raft_index enabled', function () {
    $rqlite = integration_client();
    $table = integration_table('query_raft_index');

    try {
        query_test_seed($rqlite, $table);

        // Results does not expose raft_index yet (see GO_CLIENT_PARITY.md, P1-5),
        // so verify the request succeeds and returns the expected rows.
        $query = integration_query(
            $rqlite,
            [sprintf('SELECT id, name FROM %s ORDER BY id', $table)],
            new QueryOptions(raftIndex: true),
        );

        expect($query['items'])->toHaveCount(1)
            ->and($query['rows'][0])->toEqual([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');
