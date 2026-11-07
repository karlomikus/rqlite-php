<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * Result of a query run via the execute endpoint
 */
final readonly class ExecuteResult
{
    public function __construct(
        public mixed $lastInsertId = null,
        public ?int $rowsAffected = null,
        public ?float $time = null,
    ) {
    }
}
