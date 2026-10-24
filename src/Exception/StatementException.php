<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

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
