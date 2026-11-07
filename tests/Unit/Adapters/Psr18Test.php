<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Kami\Rqlite\Adapters\PsrHttpAdapter;
use Kami\Rqlite\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientInterface;

it('sends requests and returns a response', function (): void {
    $mock = new MockHandler([new GuzzleResponse(201, ['X-Test' => 'yes'], 'response-body')]);
    $history = [];
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);
    $factory = new HttpFactory();
    $adapter = new PsrHttpAdapter('http://localhost:4001', $client, $factory, $factory, $factory);

    $response = $adapter->send(new Request('POST', '/db/query', ['timings' => true, 'empty' => null], json: ['SELECT 1']));

    expect($response->getStatusCode())->toBe(201);
    expect($response->getHeaderLine('x-test'))->toBe('yes');
    expect($response->getBody()->getContents())->toBe('response-body');

    $sent = $history[0]['request'];

    expect($sent->getMethod())->toBe('POST');
    expect((string) $sent->getUri())->toBe('http://localhost:4001/db/query?timings=1');
    expect($sent->getHeaderLine('Content-Type'))->toBe('application/json');
    expect($sent->getHeaderLine('Accept'))->toBe('application/json');
    expect((string) $sent->getBody())->toBe('["SELECT 1"]');
});

it('returns the original PSR-18 response without consuming it', function (): void {
    $original = new GuzzleResponse(202, ['X-Test' => ['one', 'two']], 'response-body', '1.0', 'Custom');
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->once()->andReturn($original);
    $factory = new HttpFactory();
    $adapter = new PsrHttpAdapter('http://localhost:4001', $client, $factory, $factory, $factory);

    $response = $adapter->send(new Request('GET', '/status'));

    expect($response)->toBe($original);
    expect($response->getProtocolVersion())->toBe('1.0');
    expect($response->getReasonPhrase())->toBe('Custom');
    expect($response->getHeader('x-test'))->toBe(['one', 'two']);
    expect($response->getBody()->tell())->toBe(0);
});

it('returns non-2xx responses instead of throwing', function (): void {
    $mock = new MockHandler([new GuzzleResponse(500, [], 'server error')]);
    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();
    $adapter = new PsrHttpAdapter('http://localhost:4001', $client, $factory, $factory, $factory);

    $response = $adapter->send(new Request('GET', '/status'));

    expect($response->getStatusCode())->toBe(500);
    expect($response->getBody()->getContents())->toBe('server error');
});

it('wraps PSR-18 transport errors', function (): void {
    $mock = new MockHandler([
        new GuzzleHttp\Exception\ConnectException(
            'connection refused',
            new GuzzleHttp\Psr7\Request('GET', 'http://localhost:4001/status'),
        ),
    ]);
    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $factory = new HttpFactory();
    $adapter = new PsrHttpAdapter('http://localhost:4001', $client, $factory, $factory, $factory);

    expect(fn (): ResponseInterface => $adapter->send(new Request('GET', '/status')))
        ->toThrow(Kami\Rqlite\Exception\TransportException::class);
});
