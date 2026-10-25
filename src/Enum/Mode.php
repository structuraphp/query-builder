<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

enum Mode: string
{
    case Equal = '=';
    case Like = 'like';
    case GreaterThan = '>';
    case LessThan = '<';
    case GreaterOrEqual = '>=';
    case LessOrEqual = '<=';
    case In = 'in';
    case IsNull = 'null';
}
