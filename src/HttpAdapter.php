<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Kami\Rqlite\Http\Request;
use Psr\Http\Message\ResponseInterface;

interface HttpAdapter
{
    /**
     * Send an HTTP request to the rqlite server.
     *
     * Implementations must throw a TransportException when the request cannot
     * be completed. HTTP error responses are returned as PSR-7 responses and are
     * handled by the client.
     *
     * @throws \Kami\Rqlite\Exception\TransportException
     */
    public function send(Request $request): ResponseInterface;
}
