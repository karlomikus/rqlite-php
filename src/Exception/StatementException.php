<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

use Kami\Rqlite\Result\SQLiteError;

/**
 * Thrown when rqlite reports an error for a specific SQL statement.
 *
 * The zero-based index of the failing statement in the submitted batch is
 * available via the $statementIndex property. The raw message reported by
 * rqlite is available via $errorMessage. If the failure originated in SQLite,
 * $sqliteError carries the result codes.
 */
final class StatementException extends RqliteException
{
    public function __construct(
        public readonly int $statementIndex,
        public readonly string $errorMessage,
        public readonly ?SQLiteError $sqliteError = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Statement %d failed: %s', $statementIndex, $errorMessage),
            0,
            $previous,
        );
    }
}
