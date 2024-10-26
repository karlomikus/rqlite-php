<?php

declare(strict_types=1);

namespace Kami\Rqlite;

interface Adapter
{
    /**
     * Get POST response body as a string
     *
     * @param string $uri URI of the request
     * @param array $body POST body
     * @param array $query Query string parameters
     * @return string Response body
     */
    public function post(string $uri, array $body = [], array $query = []): string;
}
