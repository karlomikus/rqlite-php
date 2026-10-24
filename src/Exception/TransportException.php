<?php

declare(strict_types=1);

namespace Kami\Rqlite\Exception;

/**
 * Thrown when an HTTP adapter cannot complete a request.
 *
 * This covers connection failures, timeouts, DNS/TLS problems, and similar
 * transport-level errors. HTTP error responses are not reported with this
 * exception; they are returned to the client and raised as HttpException.
 */
final class TransportException extends RqliteException
{
}
