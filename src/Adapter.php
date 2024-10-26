<?php

declare(strict_types=1);

namespace Kami\Rqlite;

use Psr\Http\Message\ResponseInterface;

interface Adapter
{
    public function post(string $uri, array $body = [], array $query = []): ResponseInterface;
}