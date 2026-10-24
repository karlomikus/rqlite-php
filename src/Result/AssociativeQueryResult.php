<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

use Generator;

/**
 * Result of a query run via the query endpoint.
 *
 * Rows are exposed as a generator and are parsed from the response stream
 * lazily. Consume all rows before advancing to the next result in the parent
 * Results stream.
 */
final class AssociativeQueryResult
{
    /**
     * @param array<string, string> $types
     * @param Generator<int, array<string, mixed>> $rows
     */
    public function __construct(
        public array $types,
        public Generator $rows,
        public ?float $time = null,
    ) {
    }
}
