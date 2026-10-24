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
 * The result and row types can be narrowed by consumers, for example:
 *
 *     /** @var Results<AssociativeQueryResult<array{id: int, name: string}>> $results *\/
 *
 * @template TResult of AssociativeQueryResult|ExecuteResult = AssociativeQueryResult|ExecuteResult
 * @implements IteratorAggregate<int, TResult>
 */
final class Results implements IteratorAggregate
{
    /** @var Generator<int, TResult> */
    public Generator $results;
    public ?float $time = null;

    /**
     * @param Generator<int, TResult>|null $results
     */
    public function __construct(?Generator $results = null)
    {
        $this->results = $results ?? self::empty();
    }

    /**
     * @return Generator<int, TResult>
     */
    public function getIterator(): Generator
    {
        yield from $this->results;
    }

    /**
     * @return Generator<int, TResult>
     */
    private static function empty(): Generator
    {
        yield from [];
    }
}
