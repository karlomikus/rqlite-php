<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

use Exception;

/**
 * Thrown when the rqlite returns an error
 */
class RqliteException extends Exception
{
    /**
     * @var array<mixed>
     */
    private array $queries;

    /**
     * @param array<mixed> $queries Array of queries that were executed
     */
    public function __construct(string $message, array $queries = [])
    {
        parent::__construct($message);

        $this->queries = $queries;
    }

    /**
     * @return array<mixed>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}
