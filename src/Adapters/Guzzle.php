<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\Adapter;
use GuzzleHttp\ClientInterface;

class Guzzle implements Adapter
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function post(string $uri, array $body = [], array $query = []): string
    {
        $response = $this->client->request('POST', $uri, [
            'json' => $body,
            'query' => $query,
        ]);

        return $response->getBody()->getContents();
    }

    public function get(string $uri, array $query = []): string
    {
        $response = $this->client->request('GET', $uri, [
            'query' => $query,
        ]);

        return $response->getBody()->getContents();
    }
}
