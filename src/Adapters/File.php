<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\Adapter;

class File implements Adapter
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    public function post(string $uri, array $body = [], array $query = []): string
    {
        $url = $this->baseUrl . $uri;
        if (count($query) > 0) {
            $url .= '?' . http_build_query($query);
        }

        $out = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($body),
            ],
        ]));

        return $out === false ? '' : (string) $out;
    }

    public function get(string $uri, array $query = []): string
    {
        $url = $this->baseUrl . $uri;
        if (count($query) > 0) {
            $url .= '?' . http_build_query($query);
        }

        $out = file_get_contents($url, false);

        return $out === false ? '' : (string) $out;
    }
}
