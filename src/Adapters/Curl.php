<?php

declare(strict_types=1);

namespace Kami\Rqlite\Adapters;

use Kami\Rqlite\Adapter;

class Curl implements Adapter
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    /**
     * @param non-empty-string $uri The URI to send the request to, relative to the base URL.
     */
    public function post(string $uri, array $body = [], array $query = []): string
    {
        $curl = curl_init();

        $url = $this->baseUrl . $uri;
        if (count($query) > 0) {
            $url .= '?' . http_build_query($query);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($body, flags: JSON_THROW_ON_ERROR),
        ]);

        $out = curl_exec($curl);

        curl_close($curl);

        return $out === false ? '' : (string) $out;
    }

    /**
     * @param non-empty-string $uri The URI to send the request to, relative to the base URL.
     */
    public function get(string $uri, array $query = []): string
    {
        $curl = curl_init();

        $url = $this->baseUrl . $uri;
        if (count($query) > 0) {
            $url .= '?' . http_build_query($query);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $out = curl_exec($curl);

        curl_close($curl);

        return $out === false ? '' : (string) $out;
    }
}
