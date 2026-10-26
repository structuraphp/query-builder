<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

/**
 * Comparison modes are words (`gte`), not symbols (`>=`), so they never
 * collide with URL characters.
 */
enum Mode: string
{
    case Equal = 'eq';
    case Like = 'like';
    case GreaterThan = 'gt';
    case LessThan = 'lt';
    case GreaterOrEqual = 'gte';
    case LessOrEqual = 'lte';
    case In = 'in';
    case IsNull = 'null';
}
