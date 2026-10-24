<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * SQLite result codes reported for a failed statement.
 *
 * @see https://www.sqlite.org/rescode.html
 */
final class SQLiteError
{
    public function __construct(
        public readonly int $code = 0,
        public readonly int $extendedCode = 0,
        public readonly int $systemErrno = 0,
    ) {
    }
}
