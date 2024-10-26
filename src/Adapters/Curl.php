<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\Adapter;

class Curl implements Adapter
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    public function post(string $uri, array $body = [], array $query = []): string
    {
        $curl = \curl_init();

        $url = $this->baseUrl . $uri;
        if (count($query) > 0) {
            $url .= '?' . http_build_query($query);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $out = curl_exec($curl);

        curl_close($curl);

        return $out;
    }
}
