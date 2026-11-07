<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Kami\Rqlite\Adapters\LoggerAdapter;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

it('logs request metadata without consuming the body or exposing URI credentials and query', function (): void {
    $stream = Utils::streamFor('secret body');
    $request = new Request('POST', 'https://user:password@example.com/path?token=secret', [], new NoSeekStream($stream));
    $response = new Response(200);
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->once()->with($request)->andReturn($response);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')->once()->with('rqlite request', [
        'method' => 'POST', 'uri' => 'https://example.com/path',
    ]);
    $logger->shouldReceive('debug')->once()->with('rqlite response', Mockery::on(
        static fn (array $context): bool => $context['status_code'] === 200
            && $context['uri'] === 'https://example.com/path',
    ));

    $decorator = new LoggerAdapter($client, $logger);
    expect($decorator->sendRequest($request))->toBe($response);
    expect($stream->tell())->toBe(0);
    expect($stream->getContents())->toBe('secret body');
});

it('logs and rethrows PSR-18 transport errors unchanged', function (): void {
    $request = new Request('GET', 'http://localhost/status');
    $error = new ConnectException('connection refused', $request);
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->once()->andThrow($error);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')->once();
    $logger->shouldReceive('error')->once()->with('rqlite request failed', Mockery::on(
        static fn (array $context): bool => $context['exception_type'] === ConnectException::class,
    ));

    $decorator = new LoggerAdapter($client, $logger);
    try {
        $decorator->sendRequest($request);
        $this->fail('Expected a transport error.');
    } catch (ConnectException $exception) {
        expect($exception)->toBe($error);
    }
});
