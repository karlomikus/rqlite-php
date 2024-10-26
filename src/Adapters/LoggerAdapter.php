<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\Adapter;
use Psr\Log\LoggerInterface;

class LoggerAdapter implements Adapter
{
    public function __construct(private readonly Adapter $baseAdapter, private readonly LoggerInterface $logger)
    {
    }

    public function post(string $uri, array $body = [], array $query = []): string
    {
        $this->logger->info($uri, [
            'body' => $body,
            'query' => $query,
        ]);

        return $this->baseAdapter->post($uri, $body, $query);
    }
}
