<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

use Kami\Rqlite\Http\Response;

final class HttpException extends RqliteException
{
    public function __construct(
        public readonly Response $response,
        ?string $message = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? sprintf('rqlite returned HTTP status %d', $response->statusCode),
            $response->statusCode,
            $previous,
        );
    }
}
