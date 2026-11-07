<?php

declare(strict_types=1);

use Kami\Rqlite\Http\Request;
use GuzzleHttp\Psr7\Response;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Adapters\LoggerAdapter;
use Kami\Rqlite\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;

it('logs requests and responses', function (): void {
    $baseAdapter = Mockery::mock(HttpAdapter::class);
    $response = new Response(200);
    $baseAdapter->shouldReceive('send')->once()->andReturn($response);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')->once()->with('rqlite request', Mockery::type('array'));
    $logger->shouldReceive('debug')->once()->with('rqlite response', Mockery::on(
        static fn (array $context): bool => $context['status_code'] === 200,
    ));

    $adapter = new LoggerAdapter($baseAdapter, $logger);

    expect($adapter->send(new Request('GET', '/status')))->toBe($response);
});

it('logs and rethrows transport errors', function (): void {
    $baseAdapter = Mockery::mock(HttpAdapter::class);
    $baseAdapter->shouldReceive('send')->once()->andThrow(new TransportException('connection refused'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')->once();
    $logger->shouldReceive('error')->once();

    $adapter = new LoggerAdapter($baseAdapter, $logger);

    expect(fn (): ResponseInterface => $adapter->send(new Request('GET', '/status')))
        ->toThrow(TransportException::class);
});
