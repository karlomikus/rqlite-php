<?php

declare(strict_types=1);

use Kami\Rqlite\RequestOptions;
use Kami\Rqlite\Result\RequestResult;

it('sends mixed read and write statements through the request endpoint', function (): void {
    $rqlite = integration_client();
    $table = integration_table('request_mixed');

    try {
        integration_execute($rqlite, [
            sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, name TEXT)', $table),
        ]);

        $results = $rqlite->request(
            [
                [sprintf('INSERT INTO %s (name) VALUES (?)', $table), 'fiona'],
                [sprintf('SELECT id, name FROM %s', $table)],
            ],
            new RequestOptions(transaction: true),
        );

        $items = [];
        $rows = [];

        foreach ($results as $result) {
            $items[] = $result;
            $rows[] = iterator_to_array($result->rows);
        }

        expect($items)->toHaveCount(2)
            ->and($items[0])->toBeInstanceOf(RequestResult::class)
            ->and($items[0]->lastInsertId)->toBeInt()->toBeGreaterThan(0)
            ->and($items[0]->rowsAffected)->toBe(1)
            ->and($rows[0])->toBe([])
            ->and($rows[1])->toEqual([
                ['id' => $items[0]->lastInsertId, 'name' => 'fiona'],
            ]);
    } finally {
        integration_drop_tables($rqlite, $table);
    }
})->group('integration');
