<?php

declare(strict_types=1);

namespace Kami\Rqlite;

interface Adapter
{
    /**
     * Get POST response body as a string
     *
     * @param string $uri URI of the request
     * @param array<mixed> $body POST body
     * @param array<mixed> $query Query string parameters
     * @return string Response body
     */
    public function post(string $uri, array $body = [], array $query = []): string;
}
