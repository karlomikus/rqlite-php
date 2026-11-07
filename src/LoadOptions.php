<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Optional settings for /db/load requests.
 */
final class LoadOptions
{
    public function __construct(
        public readonly bool $redirect = false,
    ) {
    }
}
