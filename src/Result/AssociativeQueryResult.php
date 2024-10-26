<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

readonly class AssociativeQueryResult
{
    /**
     * @param array<string> $types
     * @param array<mixed> $rows
     */
    public function __construct(public array $types = [], public array $rows = [], public ?float $time = null)
    {
    }
}
