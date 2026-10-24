<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

/**
 * Base exception for all errors raised by the rqlite client.
 *
 * Catch this type to handle any rqlite-specific failure, or catch one of the
 * more specific subclasses when the failure category matters.
 */
class RqliteException extends \RuntimeException
{
}
