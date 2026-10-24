<?php

declare(strict_types=1);

namespace Kami\Rqlite;

/**
 * Which error fields rqlite should render for a failed statement.
 *
 * The client understands both forms, so this only affects response size.
 */
enum ErrorFormat: string
{
    case V1 = 'v1';
    case V2 = 'v2';
}
