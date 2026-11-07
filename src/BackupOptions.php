<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Optional settings for /db/backup requests.
 */
final class BackupOptions
{
    /**
     * @param string|null $format "sql" for a SQL text dump; null for a binary SQLite file
     */
    public function __construct(
        public readonly ?string $format = null,
        public readonly bool $vacuum = false,
        public readonly bool $compress = false,
        public readonly bool $noLeader = false,
        public readonly bool $redirect = false,
    ) {
        if ($format !== null && $format !== 'sql') {
            throw new \InvalidArgumentException('The backup format must be "sql" or null.');
        }
    }
}
