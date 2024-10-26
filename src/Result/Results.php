<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

readonly class Results
{
    /**
     * @param array<AssociativeQueryResult|ExecuteResult> $results
     */
    public function __construct(public array $results = [], public ?float $time = null)
    {
    }
}