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
final readonly class RequestOptions
{
    public function __construct(
        public bool $transaction = false,
        public bool $pretty = false,
        public bool $timings = false,
        public bool $blobAsArray = false,
        public ?ReadConsistencyLevel $readConsistencyLevel = null,
        public int|float|string|null $timeout = null,
        public int|float|string|null $linearizableTimeout = null,
        public int|float|string|null $freshness = null,
        public bool $freshnessStrict = false,
        public bool $raftIndex = false,
        public ?ErrorFormat $errors = null,
    ) {
    }
}
