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
final class ExecuteOptions
{
    public function __construct(
        public readonly bool $transaction = false,
        public readonly bool $pretty = false,
        public readonly bool $timings = false,
        public readonly bool $queue = false,
        public readonly bool $wait = false,
        public readonly int|float|string|null $timeout = null,
        public readonly int|float|string|null $dbTimeout = null,
        public readonly bool $raftIndex = false,
        public readonly ?ErrorFormat $errors = null,
    ) {
    }
}
