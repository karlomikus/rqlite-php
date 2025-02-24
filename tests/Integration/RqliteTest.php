<?php

declare(strict_types=1);

use Kami\Rqlite\Rqlite;
use Kami\Rqlite\Adapters\Curl;
use Kami\Rqlite\Result\ExecuteResult;
use Kami\Rqlite\Exception\RqliteException;
use Kami\Rqlite\Exception\JsonDecodeException;

$curlAdapter = new Curl($_ENV['DB_URL']);
$rqlite = new Rqlite($curlAdapter);

beforeEach(function () use ($rqlite) {
    $rqlite->execute(['DROP TABLE IF EXISTS php_rqlite_test']);
    $rqlite->execute(['DROP TABLE IF EXISTS php_rqlite_test_companies']);

    $rqlite->execute(['CREATE TABLE php_rqlite_test(id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), age INTEGER)']);
    $rqlite->execute(['CREATE TABLE php_rqlite_test_companies(id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, description TEXT, no_employees INTEGER, is_active BOOLEAN)']);

    $rqlite->execute(['INSERT INTO php_rqlite_test_companies(name, description, no_employees, is_active) VALUES("Acme", "Acme Inc.", 100, true)']);
    $rqlite->execute(['INSERT INTO php_rqlite_test_companies(name, description, no_employees, is_active) VALUES("Acme2", null, 100, false)']);
});

describe('Execute integration test', function () use ($rqlite) {
    it('can execute prepared statement', function () use ($rqlite) {
        $result = $rqlite->execute([
            ['INSERT INTO php_rqlite_test(name, age) VALUES(?, ?)', 'fiona', 20]
        ]);

        expect($result->time)->toBeFloat();
        expect($result->results[0])->toBeInstanceOf(ExecuteResult::class);
        expect($result->results[0]->lastInsertId)->toBe(1);
        expect($result->results[0]->rowsAffected)->toBe(1);
        expect($result->results[0]->time)->toBeFloat();

        $dbResult = $rqlite->query([
            ['SELECT * FROM php_rqlite_test']
        ]);

        expect($dbResult->results[0]->rows[0]['id'])->toBe(1);
        expect($dbResult->results[0]->rows[0]['name'])->toBe('fiona');
        expect($dbResult->results[0]->rows[0]['age'])->toBe(20);
    });

    it('can execute prepared statement with named parameters', function () use ($rqlite) {
        $result = $rqlite->execute([
            ['INSERT INTO php_rqlite_test(name, age) VALUES(:n, :a)', ['n' => 'fiona', 'a' => 20]]
        ]);

        expect($result->time)->toBeFloat();
        expect($result->results[0])->toBeInstanceOf(ExecuteResult::class);
        expect($result->results[0]->lastInsertId)->toBe(1);
        expect($result->results[0]->rowsAffected)->toBe(1);
        expect($result->results[0]->time)->toBeFloat();

        $dbResult = $rqlite->query([
            ['SELECT * FROM php_rqlite_test']
        ]);

        expect($dbResult->results[0]->rows[0]['id'])->toBe(1);
        expect($dbResult->results[0]->rows[0]['name'])->toBe('fiona');
        expect($dbResult->results[0]->rows[0]['age'])->toBe(20);
    });
});

describe('Query integration test', function () use ($rqlite) {
    it('can return multiple results', function () use ($rqlite) {
        $query = $rqlite->query([
            ['SELECT * FROM php_rqlite_test_companies'],
            ['SELECT name FROM php_rqlite_test_companies WHERE is_active = ?', true],
        ]);

        expect($query->results)->toHaveCount(2);
        expect($query->results[0]->rows)->toHaveCount(2);
        expect($query->results[1]->rows)->toHaveCount(1);
    });

    it('throws rqlite error', function () use ($rqlite) {
        $rqlite->query([
            ['SELECT * FROM non_existing_table'],
            ['SELECT name FROM php_rqlite_test_companies WHERE is_active = ?', true],
        ]);
    })->throws(RqliteException::class);

    it('throws error on invalid rqlite response', function () {
        $curlAdapter = new Curl('');
        $rqlite = new Rqlite($curlAdapter);

        $rqlite->query([
            ['SELECT * FROM non_existing_table'],
        ]);
    })->throws(JsonDecodeException::class);
});
