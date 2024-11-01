<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

final class AssociativeQueryResult
{
    /**
     * @param array<string> $types
     * @param array<mixed> $rows
     */
    public function __construct(public readonly array $types = [], public readonly array $rows = [], public readonly ?float $time = null)
    {
    }
}
