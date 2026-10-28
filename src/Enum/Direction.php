<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

enum Direction: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
