<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

enum PaginationMode: string
{
    case Offset = 'offset';
    case Cursor = 'cursor';
    case None = 'none';
}
