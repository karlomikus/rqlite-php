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

it('omits false boolean query parameters', function () {
    $request = new Request('POST', '/db/execute', [
        'timings' => false,
        'transaction' => true,
        'associative' => false,
    ]);

    expect($request->query())->toBe(['transaction' => true]);
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

it('accepts a stream body and exposes it', function () {
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, 'binary-data');
    rewind($stream);

    $request = new Request('POST', '/db/load', ['redirect' => true], ['Content-Type' => 'text/plain'], stream: $stream);

    expect($request->stream)->toBe($stream);
    expect($request->json)->toBeNull();
    expect($request->rawBody)->toBeNull();
    expect($request->body())->toBeNull();
    expect($request->headers())->toBe([
        'Content-Type' => 'text/plain',
        'Accept' => 'application/json',
    ]);
    expect($request->query())->toBe(['redirect' => true]);

    fclose($stream);
});

it('does not add a content type header for stream requests', function () {
    $stream = fopen('php://temp', 'wb');
    $request = new Request('POST', '/boot', stream: $stream);

    expect($request->headers())->toBe(['Accept' => 'application/json']);

    fclose($stream);
});

it('rejects requests with both a json payload and a stream', function () {
    $stream = fopen('php://temp', 'wb');

    new Request('POST', '/db/load', json: [], stream: $stream);

    fclose($stream);
})->throws(InvalidArgumentException::class);

it('rejects requests with both a raw body and a stream', function () {
    $stream = fopen('php://temp', 'wb');

    new Request('POST', '/db/load', rawBody: 'SELECT 1', stream: $stream);

    fclose($stream);
})->throws(InvalidArgumentException::class);

it('rejects a non stream body stream', function () {
    new Request('POST', '/boot', stream: 'not-a-resource');
})->throws(InvalidArgumentException::class);

it('rejects an empty method', function () {
    new Request('', '/db/query');
})->throws(InvalidArgumentException::class);

it('rejects an empty uri', function () {
    new Request('GET', '');
})->throws(InvalidArgumentException::class);
