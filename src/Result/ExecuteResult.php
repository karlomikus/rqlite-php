<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * Result of a query run via the execute endpoint
 */
final class ExecuteResult
{
    public function __construct(
        public readonly mixed $lastInsertId = null,
        public readonly ?int $rowsAffected = null,
        public readonly ?float $time = null,
    ) {
    }
}
