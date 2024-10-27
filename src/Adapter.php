<?php

declare(strict_types=1);

namespace Kami\Rqlite;

interface Adapter
{
    /**
     * Return POST response body as a string
     *
     * @param string $uri URI of the request
     * @param array<mixed> $body POST body
     * @param array<mixed> $query Query string parameters
     * @return string Response body
     */
    public function post(string $uri, array $body = [], array $query = []): string;

    /**
     * Return GET response body as a string
     *
     * @param string $uri URI of the request
     * @param array<mixed> $query Query string parameters
     * @return string Response body
     */
    public function get(string $uri, array $query = []): string;
}
