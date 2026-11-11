<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;

it('connects to a real node with the default Guzzle factory', function (): void {
    $baseUrl = getenv('RQLITE_URL') ?: 'http://myrqlite-service-1:4001';
    $status = Rqlite::withGuzzle($baseUrl)->status();

    expect($status['store']['ready'] ?? null)->toBeTrue();
})->group('integration');

it('loads SQL and streams a backup from a real node', function (): void {
    $rqlite = integration_client();
    $table = integration_table('stream_load');

    try {
        $rqlite->load(sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY);', $table));
        $rows = integration_rows($rqlite, [[
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            $table,
        ]]);
        expect($rows)->toHaveCount(1);

        $body = $rqlite->backup();
        expect($body->read(16))->toBe("SQLite format 3\0");
        $body->close();
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');
