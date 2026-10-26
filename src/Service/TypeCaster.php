<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Carbon\CarbonImmutable;
use StructuraPhp\QueryBuilder\Enum\Type;
use UnexpectedValueException;

/**
 * The validation rules of a type and the cast they make safe.
 */
final class TypeCaster
{
    /**
     * @return list<string>
     */
    public static function rules(Type $type): array
    {
        return match ($type) {
            Type::String => ['string'],
            Type::Integer => ['integer'],
            Type::Float => ['numeric'],
            Type::Boolean => ['boolean'],
            Type::Date => ['date_format:Y-m-d'],
        };
    }

    public static function cast(Type $type, mixed $value): mixed
    {
        $value = self::scalar($value);

        return match ($type) {
            Type::String => (string) $value,
            Type::Integer => (int) $value,
            Type::Float => (float) $value,
            Type::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            Type::Date => CarbonImmutable::createFromFormat(
                '!Y-m-d',
                (string) $value,
            ),
        };
    }

    /**
     * Validated input only reaches here as a scalar: anything else is a bug
     * upstream.
     */
    public static function scalar(mixed $value): bool|float|int|string
    {
        if (!\is_scalar($value)) {
            throw new UnexpectedValueException(\sprintf(
                'Expected a scalar, got %s.',
                get_debug_type($value),
            ));
        }

        return $value;
    }
}
