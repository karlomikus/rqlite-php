<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Kami\Rqlite\Exception\HttpException;
use Kami\Rqlite\Exception\TransportException;
use Kami\Rqlite\QueryOptions;
use Kami\Rqlite\Rqlite;

it('creates a Guzzle-backed client with the default transport', function (): void {
    expect(Rqlite::withGuzzle('http://localhost:4001'))->toBeInstanceOf(Rqlite::class);
});

it('uses an injected PSR-18 client with Guzzle request factories', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"store":{"ready":true}}')]));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack, 'stream' => true]);
    $rqlite = Rqlite::withGuzzle('http://localhost:4001/prefix', $client);

    expect($rqlite->status())->toBe(['store' => ['ready' => true]]);
    expect((string) $history[0]['request']->getUri())->toBe('http://localhost:4001/prefix/status');
    expect($history[0]['request']->getHeaderLine('Accept'))->toBe('application/json');
});

it('validates the base URL in the Guzzle factory', function (): void {
    expect(fn () => Rqlite::withGuzzle('/relative'))->toThrow(InvalidArgumentException::class);
});

it('accepts a PSR-18 client that is not Guzzle', function (): void {
    $client = Mockery::mock(\Psr\Http\Client\ClientInterface::class);
    $client->shouldReceive('sendRequest')->once()->andReturn(new Response(200, [], '{"ready":true}'));

    expect(Rqlite::withGuzzle('http://localhost:4001', $client)->status())->toBe(['ready' => true]);
});

it('sends an absolute PSR-7 JSON request with a base path and filtered flags', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"results":[]}')]));
    $stack->push(Middleware::history($history));
    $factory = new HttpFactory();
    $rqlite = new Rqlite('https://example.com:8443/prefix/', new Client(['handler' => $stack]), $factory, $factory);

    iterator_to_array($rqlite->query(['SELECT 1'], new QueryOptions(timings: true, timeout: '500ms')));
    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://example.com:8443/prefix/db/query?timings=1&associative=1&timeout=500ms');
    expect($request->getHeaderLine('Accept'))->toBe('application/json');
    expect($request->getHeaderLine('Content-Type'))->toBe('application/json');
    expect((string) $request->getBody())->toBe('["SELECT 1"]');
});

it('rejects invalid or ambiguous base URLs', function (string $url): void {
    $factory = new HttpFactory();
    expect(fn () => new Rqlite($url, new Client(), $factory, $factory))
        ->toThrow(InvalidArgumentException::class);
})->with(['', '/relative', 'ftp://example.com', 'http://example.com/path?token=x', 'http://example.com/path#fragment']);

it('returns the original response on PSR-18 HTTP errors', function (): void {
    $response = new Response(503, ['X-Test' => 'yes'], 'unavailable');
    $client = Mockery::mock(\Psr\Http\Client\ClientInterface::class);
    $client->shouldReceive('sendRequest')->once()->andReturn($response);
    $factory = new HttpFactory();
    $rqlite = new Rqlite('http://localhost:4001', $client, $factory, $factory);

    try {
        $rqlite->status();
        $this->fail('Expected an HTTP exception.');
    } catch (HttpException $exception) {
        expect($exception->response)->toBe($response);
        expect($response->getBody()->getContents())->toBe('unavailable');
    }
});

it('wraps PSR-18 errors on raw upload requests', function (): void {
    $request = new \GuzzleHttp\Psr7\Request('POST', 'http://localhost:4001/boot');
    $cause = new \GuzzleHttp\Exception\ConnectException('unavailable', $request);
    $client = Mockery::mock(\Psr\Http\Client\ClientInterface::class);
    $client->shouldReceive('sendRequest')->once()->andThrow($cause);
    $factory = new HttpFactory();
    $rqlite = new Rqlite('http://localhost:4001', $client, $factory, $factory);

    try {
        $rqlite->boot('SQLite format fake data');
        $this->fail('Expected a transport exception.');
    } catch (TransportException $exception) {
        expect($exception->getPrevious())->toBe($cause);
    }
});
