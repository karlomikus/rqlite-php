<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

use Generator;
use IteratorAggregate;

/**
 * Stream of results from a query run.
 *
 * The top-level time is populated only after the stream has been fully
 * consumed. Query result rows are streams as well and must be consumed before
 * advancing to the next result.
 *
 * @implements IteratorAggregate<int, AssociativeQueryResult|ExecuteResult>
 */
final class Results implements IteratorAggregate
{
    /** @var Generator<int, AssociativeQueryResult|ExecuteResult> */
    public Generator $results;
    public ?float $time = null;

    /**
     * @param Generator<int, AssociativeQueryResult|ExecuteResult>|null $results
     */
    public function __construct(?Generator $results = null)
    {
        $this->results = $results ?? self::empty();
    }

    public function getIterator(): Generator
    {
        yield from $this->results;
    }

    /**
     * @return Generator<int, AssociativeQueryResult|ExecuteResult>
     */
    private static function empty(): Generator
    {
        yield from [];
    }
}
