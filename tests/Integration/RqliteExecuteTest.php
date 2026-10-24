<?php

declare(strict_types=1);

use Kami\Rqlite\ExecuteOptions;
use Kami\Rqlite\Result\ExecuteResult;

/*
|--------------------------------------------------------------------------
| Execute integration test
|--------------------------------------------------------------------------
|
| Mirrors the scenarios covered by Test_Execute in the Go HTTP client's
| http_test.go, run against the real cluster defined in docker-compose.yml.
|
|     docker compose run --rm php vendor/bin/pest --group=integration
|
| When running the test outside the PHP container, override the node URL:
|
|     RQLITE_URL=http://localhost:4001 vendor/bin/pest --group=integration
|
*/

it('executes a single CREATE TABLE statement', function () {
    $rqlite = integration_client();
    $table = integration_table('single_create');

    try {
        $results = integration_execute($rqlite, [
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT)', $table),
        ]);

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(ExecuteResult::class);

        expect(integration_rows($rqlite, [[
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            $table,
        ]]))->toHaveCount(1);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');

it('executes a single CREATE TABLE statement with options', function () {
    $rqlite = integration_client();
    $table = integration_table('create_with_options');

    try {
        $results = integration_execute(
            $rqlite,
            [sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT)', $table)],
            new ExecuteOptions(transaction: true, timeout: '1s'),
        );

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(ExecuteResult::class);

        expect(integration_rows($rqlite, [[
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            $table,
        ]]))->toHaveCount(1);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');

it('executes two CREATE TABLE statements in one request', function () {
    $rqlite = integration_client();
    $foo = integration_table('two_create_foo');
    $bar = integration_table('two_create_bar');

    try {
        $results = integration_execute($rqlite, [
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT)', $foo),
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT)', $bar),
        ]);

        expect($results)->toHaveCount(2)
            ->and($results[0])->toBeInstanceOf(ExecuteResult::class)
            ->and($results[1])->toBeInstanceOf(ExecuteResult::class);

        $tables = integration_rows($rqlite, [[
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN (?, ?) ORDER BY name",
            $foo,
            $bar,
        ]]);

        expect($tables)->toHaveCount(2);
    } finally {
        integration_drop_tables($rqlite, $foo, $bar);
    }
})->group('integration');

it('executes a single INSERT statement with positional arguments', function () {
    $rqlite = integration_client();
    $table = integration_table('positional');

    try {
        integration_execute($rqlite, [
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)', $table),
        ]);

        $results = integration_execute($rqlite, [
            [sprintf('INSERT INTO %s (name, age) VALUES (?, ?)', $table), 'fiona', 30],
        ]);

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(ExecuteResult::class)
            ->and($results[0]->rowsAffected)->toBe(1)
            ->and($results[0]->lastInsertId)->toBeInt()->toBeGreaterThan(0);

        $rows = integration_rows($rqlite, [
            sprintf('SELECT id, name, age FROM %s', $table),
        ]);

        expect($rows)->toEqual([
            ['id' => $results[0]->lastInsertId, 'name' => 'fiona', 'age' => 30],
        ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');

it('executes a single INSERT statement with named arguments', function () {
    $rqlite = integration_client();
    $table = integration_table('named');

    try {
        integration_execute($rqlite, [
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)', $table),
        ]);

        $results = integration_execute($rqlite, [
            [
                sprintf('INSERT INTO %s (name, age) VALUES (:name, :age)', $table),
                ['name' => 'bob', 'age' => 25],
            ],
        ]);

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(ExecuteResult::class)
            ->and($results[0]->rowsAffected)->toBe(1)
            ->and($results[0]->lastInsertId)->toBeInt()->toBeGreaterThan(0);

        $rows = integration_rows($rqlite, [
            sprintf('SELECT id, name, age FROM %s', $table),
        ]);

        expect($rows)->toEqual([
            ['id' => $results[0]->lastInsertId, 'name' => 'bob', 'age' => 25],
        ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');

it('executes a single INSERT statement with raft_index enabled', function () {
    $rqlite = integration_client();
    $table = integration_table('raft_index');

    try {
        integration_execute($rqlite, [
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)', $table),
        ]);

        // Results does not expose raft_index yet (see GO_CLIENT_PARITY.md, P1-5),
        // so verify the request succeeds and the write is applied.
        $results = integration_execute(
            $rqlite,
            [[sprintf('INSERT INTO %s (name, age) VALUES (?, ?)', $table), 'fiona', 30]],
            new ExecuteOptions(raftIndex: true),
        );

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(ExecuteResult::class)
            ->and($results[0]->rowsAffected)->toBe(1)
            ->and($results[0]->lastInsertId)->toBeInt()->toBeGreaterThan(0);

        $rows = integration_rows($rqlite, [
            sprintf('SELECT id, name, age FROM %s', $table),
        ]);

        expect($rows)->toEqual([
            ['id' => $results[0]->lastInsertId, 'name' => 'fiona', 'age' => 30],
        ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');
