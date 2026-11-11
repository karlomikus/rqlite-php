<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Throwable;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class LoggerAdapter implements ClientInterface
{
    public function __construct(private readonly ClientInterface $client, private readonly LoggerInterface $logger)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $startedAt = hrtime(true);
        // Avoid exposing credentials or query values in logs.
        $uri = (string) $request->getUri()->withUserInfo('')->withQuery('')->withFragment('');

        $this->logger->debug('rqlite request', [
            'method' => $request->getMethod(),
            'uri' => $uri,
        ]);

        try {
            $response = $this->client->sendRequest($request);
        } catch (Throwable $exception) {
            $this->logger->error('rqlite request failed', [
                'method' => $request->getMethod(),
                'uri' => $uri,
                'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
                'exception_type' => $exception::class,
            ]);

            throw $exception;
        }

        $this->logger->debug('rqlite response', [
            'method' => $request->getMethod(),
            'uri' => $uri,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
        ]);

        return $response;
    }
}
