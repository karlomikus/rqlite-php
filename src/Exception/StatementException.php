<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

/**
 * Thrown when rqlite reports an error for a specific SQL statement.
 *
 * The zero-based index of the failing statement in the submitted batch is
 * available via the $statementIndex property.
 */
final class StatementException extends RqliteException
{
    public function __construct(
        public readonly int $statementIndex,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Statement %d failed: %s', $statementIndex, $message),
            0,
            $previous,
        );
    }
}
