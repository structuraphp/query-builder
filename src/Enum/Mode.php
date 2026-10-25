<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

use LogicException;

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

    public function operator(): string
    {
        return match ($this) {
            self::Equal => '=',
            self::GreaterThan => '>',
            self::LessThan => '<',
            self::GreaterOrEqual => '>=',
            self::LessOrEqual => '<=',
            default => throw new LogicException(
                "Mode {$this->value} is not a comparison.",
            ),
        };
    }
}
