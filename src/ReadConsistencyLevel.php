<?php

declare(strict_types=1);

namespace Kami\Rqlite;

enum ReadConsistencyLevel: string
{
    case None = 'none';
    case Weak = 'weak';
    case Strong = 'strong';
}