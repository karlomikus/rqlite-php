<?php

declare(strict_types=1);

namespace Kami\Rqlite\Result;

use Generator;

/**
 * Result of a statement run via the unified request endpoint.
 *
 * Read statements populate `types` and `rows`; write statements populate
 * `lastInsertId` and `rowsAffected`. Rows are parsed lazily and must be
 * consumed before advancing to the next result in the parent Results stream.
 *
 * @template TRow of array<string, mixed> = array<string, mixed>
 */
final class RequestResult
{
    /**
     * @param array<string, string> $types
     * @param Generator<int, TRow> $rows
     */
    public function __construct(
        public array $types,
        public Generator $rows,
        public mixed $lastInsertId = null,
        public ?int $rowsAffected = null,
        public ?float $time = null,
    ) {
    }
}
