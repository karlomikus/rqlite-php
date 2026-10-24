<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Kami\Rqlite\Http\Request;
use Kami\Rqlite\Http\Response;

interface HttpAdapter
{
    /**
     * Send an HTTP request to the rqlite server.
     *
     * Implementations must throw a TransportException when the request cannot
     * be completed. HTTP error responses are returned as a Response and are
     * handled by the client.
     *
     * @throws \Kami\Rqlite\Exception\TransportException
     */
    public function send(Request $request): Response;
}
