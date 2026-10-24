<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Throwable;
use Psr\Log\LoggerInterface;
use Kami\Rqlite\HttpAdapter;
use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;

class LoggerAdapter implements HttpAdapter
{
    public function __construct(private readonly HttpAdapter $baseAdapter, private readonly LoggerInterface $logger)
    {
    }

    public function send(Request $request): Response
    {
        $startedAt = hrtime(true);

        $this->logger->debug('rqlite request', [
            'method' => $request->method,
            'uri' => $request->uri,
            'query' => $request->query(),
            'body' => $request->json ?? $request->rawBody,
        ]);

        try {
            $response = $this->baseAdapter->send($request);
        } catch (Throwable $exception) {
            $this->logger->error('rqlite request failed', [
                'method' => $request->method,
                'uri' => $request->uri,
                'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $this->logger->debug('rqlite response', [
            'method' => $request->method,
            'uri' => $request->uri,
            'status_code' => $response->statusCode,
            'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
        ]);

        return $response;
    }
}
