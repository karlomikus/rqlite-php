<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Optional settings for /db/request requests.
 *
 * Queued writes (`queue` / `wait`) are not supported by the unified endpoint.
 * Durations may be provided as duration strings (for example "500ms", "2s",
 * or "1m"), or as a number of seconds. Numeric values are sent as seconds.
 */
final class RequestOptions
{
    public function __construct(
        public readonly bool $transaction = false,
        public readonly bool $pretty = false,
        public readonly bool $timings = false,
        public readonly bool $blobAsArray = false,
        public readonly ?ReadConsistencyLevel $readConsistencyLevel = null,
        public readonly int|float|string|null $timeout = null,
        public readonly int|float|string|null $linearizableTimeout = null,
        public readonly int|float|string|null $freshness = null,
        public readonly bool $freshnessStrict = false,
        public readonly bool $raftIndex = false,
        public readonly ?ErrorFormat $errors = null,
    ) {
    }
}
