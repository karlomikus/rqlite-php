<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

/**
 * Collection of results from a query run
 */
final class Results
{
    /**
     * @param array<AssociativeQueryResult|ExecuteResult> $results
     */
    public function __construct(public readonly array $results = [], public readonly ?float $time = null)
    {
    }
}
