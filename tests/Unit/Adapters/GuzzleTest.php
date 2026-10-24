<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Kami\Rqlite\Adapters\Guzzle;
use Kami\Rqlite\Http\Request;

it('sends requests and returns a response', function () {
    $mock = new MockHandler([new GuzzleResponse(201, ['X-Test' => 'yes'], 'response-body')]);
    $history = [];
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack, 'base_uri' => 'http://localhost:4001']);
    $adapter = new Guzzle($client);

    $response = $adapter->send(new Request('POST', '/db/query', ['timings' => true, 'empty' => null], json: ['SELECT 1']));

    expect($response->statusCode)->toBe(201);
    expect($response->header('x-test'))->toBe('yes');
    expect($response->text())->toBe('response-body');

    $sent = $history[0]['request'];

    expect($sent->getMethod())->toBe('POST');
    expect((string) $sent->getUri())->toBe('http://localhost:4001/db/query?timings=1');
    expect($sent->getHeaderLine('Content-Type'))->toBe('application/json');
    expect($sent->getHeaderLine('Accept'))->toBe('application/json');
    expect((string) $sent->getBody())->toBe('["SELECT 1"]');
});

it('returns non-2xx responses instead of throwing', function () {
    $mock = new MockHandler([new GuzzleResponse(500, [], 'server error')]);
    $client = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'http://localhost:4001']);
    $adapter = new Guzzle($client);

    $response = $adapter->send(new Request('GET', '/status'));

    expect($response->statusCode)->toBe(500);
    expect($response->text())->toBe('server error');
});

it('wraps guzzle transport errors', function () {
    $mock = new MockHandler([
        new GuzzleHttp\Exception\ConnectException(
            'connection refused',
            new GuzzleHttp\Psr7\Request('GET', 'http://localhost:4001/status'),
        ),
    ]);
    $client = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'http://localhost:4001']);
    $adapter = new Guzzle($client);

    expect(fn () => $adapter->send(new Request('GET', '/status')))
        ->toThrow(Kami\Rqlite\Exception\TransportException::class);
});
