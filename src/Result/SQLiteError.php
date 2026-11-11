<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * SQLite result codes reported for a failed statement.
 *
 * @see https://www.sqlite.org/rescode.html
 */
final readonly class SQLiteError
{
    public function __construct(
        public int $code = 0,
        public int $extendedCode = 0,
        public int $systemErrno = 0,
    ) {
    }
}
