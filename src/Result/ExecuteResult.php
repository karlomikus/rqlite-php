<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

readonly class ExecuteResult
{
    public function __construct(
        private ?string $error = null,
        private ?int $lastInsertId = null,
        private ?int $rowsAffected = null,
        private ?float $time = null,
    ) {
    }
}