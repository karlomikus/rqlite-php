<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Optional settings for /db/backup requests.
 */
final readonly class BackupOptions
{
    /**
     * @param string|null $format "sql" for a SQL text dump; null for a binary SQLite file
     */
    public function __construct(
        public ?string $format = null,
        public bool $vacuum = false,
        public bool $compress = false,
        public bool $noLeader = false,
        public bool $redirect = false,
    ) {
        if ($format !== null && $format !== 'sql') {
            throw new \InvalidArgumentException('The backup format must be "sql" or null.');
        }
    }
}
