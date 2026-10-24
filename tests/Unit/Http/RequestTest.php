<?php

declare(strict_types=1);

use Kami\Rqlite\Http\Request;

it('adds json headers and encodes the body', function () {
    $request = new Request('POST', '/db/query', ['timings' => true, 'level' => null], json: ['SELECT 1']);

    expect($request->query())->toBe(['timings' => true]);
    expect($request->headers())->toBe([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ]);
    expect($request->body())->toBe('["SELECT 1"]');
});

it('does not add a json content type for raw bodies', function () {
    $request = new Request('POST', '/db/load', rawBody: 'CREATE TABLE foo (id INTEGER)');

    expect($request->headers())->toBe(['Accept' => 'application/json']);
    expect($request->body())->toBe('CREATE TABLE foo (id INTEGER)');
});

it('keeps custom headers and matches them case-insensitively', function () {
    $request = new Request('POST', '/db/query', headers: ['content-type' => 'text/plain'], json: ['SELECT 1']);

    expect($request->headers())->toBe([
        'content-type' => 'text/plain',
        'Accept' => 'application/json',
    ]);
});

it('rejects requests with both json and a raw body', function () {
    new Request('POST', '/db/query', json: [], rawBody: 'SELECT 1');
})->throws(InvalidArgumentException::class);

it('rejects an empty method', function () {
    new Request('', '/db/query');
})->throws(InvalidArgumentException::class);

it('rejects an empty uri', function () {
    new Request('GET', '');
})->throws(InvalidArgumentException::class);
