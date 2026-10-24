<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

/**
 * Thrown when a successful rqlite response cannot be decoded.
 *
 * This indicates invalid JSON, an unexpected top-level value, or a response
 * shape that does not match what the client expects.
 */
final class DecodingException extends RqliteException
{
}
