<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * Result of a query run via the execute endpoint
 */
final readonly class ExecuteResult
{
    public function __construct(
        public ?string $error = null,
        public ?int $lastInsertId = null,
        public ?int $rowsAffected = null,
        public ?float $time = null,
    ) {
    }
}
