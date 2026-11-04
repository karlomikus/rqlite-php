<?php

declare(strict_types=1);

use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\Exception\StatementException;
use Kami\Rqlite\ExecuteOptions;
use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\RequestOptions;
use Kami\Rqlite\Rqlite;

require __DIR__ . '/vendor/autoload.php';

/**
 * A small command line demo for the karlomikus/rqlite PHP client.
 *
 * It manages a tiny "tasks" table and walks through the main features of the
 * library: status, execute, query with parameters, transactions, the unified
 * request endpoint, and error handling.
 *
 * Run it with:
 *
 *   docker compose run --rm php php index.php
 */

$baseUrl = getenv('RQLITE_URL') ?: 'http://localhost:4001';

$rqlite = new Rqlite(new Curl($baseUrl));

echo "Connected to rqlite at {$baseUrl}\n";

// --- Server status -----------------------------------------------------------

$status = $rqlite->status();
$version = $status['build']['version'] ?? 'unknown';

echo "Server version: {$version}\n\n";

// --- Schema (execute endpoint) -----------------------------------------------

$rqlite->execute([
    'DROP TABLE IF EXISTS tasks',
    'CREATE TABLE tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        done INTEGER NOT NULL DEFAULT 0,
        status TEXT
    )',
]);

echo "Schema is ready.\n";

// --- Writes (execute endpoint, transaction) ----------------------------------

// Statement arrays are [sql, positional params] or [sql, named params map].
// Wrap all statements in a single transaction with ExecuteOptions.
$results = $rqlite->execute(
    [
        ['INSERT INTO tasks (title) VALUES (?)', 'Write the rqlite PHP example'],
        ['INSERT INTO tasks (title) VALUES (?)', 'Try the streaming results'],
        ['INSERT INTO tasks (title) VALUES (:title)', ['title' => 'Ship it']],
        ['INSERT INTO tasks (title, status) VALUES (:title, :status)', ['title' => 'Ship it', 'status' => 'in progress']],
    ],
    new ExecuteOptions(transaction: true),
);

foreach ($results as $result) {
    echo sprintf(
        "Inserted task #%d (%d row(s) affected)\n",
        $result->lastInsertId ?? 0,
        $result->rowsAffected ?? 0,
    );
}

echo "\n";

// --- Reads (query endpoint) ---------------------------------------------------

// Results and rows are streamed as generators. Always consume a result's rows
// before advancing to the next result (nested foreach does this naturally).
$results = $rqlite->query(
    ['SELECT id, title, done, status FROM tasks ORDER BY id'],
    new QueryOptions(timings: true),
);

foreach ($results as $result) {
    echo "Open tasks:\n";

    foreach ($result->rows as $row) {
        echo sprintf("  [%d] %s (done: %d) (status: %s)\n", $row['id'], $row['title'], $row['done'], $row['status']);
    }

    echo sprintf("  (query took %.6fs)\n", $result->time ?? 0.0);
}

// The top-level time is populated after the stream is fully consumed.
echo sprintf("Total server time: %.6fs\n\n", $results->time ?? 0.0);

// --- Parameters (query endpoint) ---------------------------------------------

$results = $rqlite->query([
    ['SELECT id, title FROM tasks WHERE id = :id', ['id' => 1]],
]);

foreach ($results as $result) {
    foreach ($result->rows as $row) {
        echo "Fetched by named parameter: #{$row['id']} {$row['title']}\n";
    }
}

// --- Unified request endpoint (reads and writes in one call) ------------------

$results = $rqlite->request(
    [
        ['INSERT INTO tasks (title) VALUES (?)', 'Added via /db/request'],
        ['SELECT id, title FROM tasks WHERE done = 0 ORDER BY id DESC'],
    ],
    new RequestOptions(transaction: true),
);

foreach ($results as $result) {
    if ($result->lastInsertId !== null) {
        echo "Request endpoint inserted task #{$result->lastInsertId}\n";
    }

    foreach ($result->rows as $row) {
        echo "Request endpoint row: [{$row['id']}] {$row['title']}\n";
    }
}

echo "\n";

// --- Error handling ----------------------------------------------------------

// The client throws StatementException on the first failing statement.
try {
    $rqlite->execute([
        'INSERT INTO tasks (id, title) VALUES (1, \'duplicate id\')',
    ]);
} catch (StatementException $e) {
    echo "Caught expected statement error: {$e->getMessage()}\n";
    echo "  statement index: {$e->statementIndex}\n";

    if ($e->sqliteError !== null) {
        echo "  sqlite error code: {$e->sqliteError->code}\n";
    }
}

echo "\nDone.\n";
