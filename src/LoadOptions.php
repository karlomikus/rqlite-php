<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Optional settings for /db/load requests.
 */
final readonly class LoadOptions
{
    public function __construct(
        public bool $redirect = false,
    ) {
    }
}
