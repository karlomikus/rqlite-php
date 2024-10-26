<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * Collection of results from a query run
 */
readonly class Results
{
    /**
     * @param array<AssociativeQueryResult|ExecuteResult> $results
     */
    public function __construct(public array $results = [], public ?float $time = null)
    {
    }
}
