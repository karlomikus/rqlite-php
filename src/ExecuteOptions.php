<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Optional settings for /db/execute requests.
 *
 * `timeout` is the request-forwarding timeout understood by rqlite. `dbTimeout`
 * is the per-statement SQLite timeout, sent as `db_timeout`.
 *
 * Durations may be provided as duration strings (for example "500ms", "2s",
 * or "1m"), or as a number of seconds. Numeric values are sent as seconds.
 */
final readonly class ExecuteOptions
{
    public function __construct(
        public bool $transaction = false,
        public bool $pretty = false,
        public bool $timings = false,
        public bool $queue = false,
        public bool $wait = false,
        public int|float|string|null $timeout = null,
        public int|float|string|null $dbTimeout = null,
        public bool $raftIndex = false,
        public ?ErrorFormat $errors = null,
    ) {
    }
}
