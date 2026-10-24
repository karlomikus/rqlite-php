<?php

declare(strict_types=1);

use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Adapters\LoggerAdapter;
use Kami\Rqlite\Exception\TransportException;
use Psr\Log\LoggerInterface;

it('logs requests and responses', function () {
    $baseAdapter = Mockery::mock(HttpAdapter::class);
    $baseAdapter->shouldReceive('send')->once()->andReturn(new Response(200));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')->twice();

    $adapter = new LoggerAdapter($baseAdapter, $logger);

    expect($adapter->send(new Request('GET', '/status'))->statusCode)->toBe(200);
});

it('logs and rethrows transport errors', function () {
    $baseAdapter = Mockery::mock(HttpAdapter::class);
    $baseAdapter->shouldReceive('send')->once()->andThrow(new TransportException('connection refused'));

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')->once();
    $logger->shouldReceive('error')->once();

    $adapter = new LoggerAdapter($baseAdapter, $logger);

    expect(fn () => $adapter->send(new Request('GET', '/status')))
        ->toThrow(TransportException::class);
});
