<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use LogicException;
use StructuraPhp\QueryBuilder\Enum\Mode;
use StructuraPhp\QueryBuilder\Enum\Type;

/**
 * Applies a filter value to a query, by mode.
 */
final class FilterApplier
{
    /**
     * @return list<Mode>
     */
    public static function modes(Type $type): array
    {
        $comparisons = [
            Mode::Equal,
            Mode::GreaterThan,
            Mode::LessThan,
            Mode::GreaterOrEqual,
            Mode::LessOrEqual,
        ];

        return match ($type) {
            Type::String => [Mode::Equal, Mode::Like, Mode::In],
            Type::Boolean => [Mode::Equal],
            Type::Integer, Type::Float => [...$comparisons, Mode::In],
            Type::Date => $comparisons,
        };
    }

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     */
    public static function apply(
        Builder $query,
        Type $type,
        string $column,
        Mode $mode,
        mixed $value,
    ): void {
        $base = $query->getQuery();

        if ($mode === Mode::IsNull) {
            $base->whereNull($column);

            return;
        }

        if ($mode === Mode::In) {
            $base->whereIn($column, \is_array($value) ? $value : [$value]);

            return;
        }

        if ($mode === Mode::Like) {
            self::like($query, $column, (string) TypeCaster::scalar($value));

            return;
        }

        if ($type === Type::Date && $value instanceof CarbonImmutable) {
            foreach (self::day($mode, $value) as $operator => $bound) {
                $base->where($column, $operator, $bound);
            }

            return;
        }

        $base->where($column, self::operator($mode), $value);
    }

    public static function operator(Mode $mode): string
    {
        return match ($mode) {
            Mode::Equal => '=',
            Mode::GreaterThan => '>',
            Mode::LessThan => '<',
            Mode::GreaterOrEqual => '>=',
            Mode::LessOrEqual => '<=',
            default => throw new LogicException(
                "Mode {$mode->value} is not a comparison.",
            ),
        };
    }

    /**
     * Bounds on the column itself rather than `DATE(column)`, so an index on
     * it stays usable. Bounds are `Y-m-d` strings: SQLite compares as text.
     *
     * @return array<string, string>
     */
    private static function day(Mode $mode, CarbonImmutable $day): array
    {
        $start = $day->toDateString();
        $next = $day->addDay()->toDateString();

        return match ($mode) {
            Mode::Equal => ['>=' => $start, '<' => $next],
            Mode::GreaterThan => ['>=' => $next],
            Mode::LessOrEqual => ['<' => $next],
            default => [self::operator($mode) => $start],
        };
    }

    /**
     * `whereLike()` cannot add an ESCAPE clause, which SQLite needs to match
     * `%` and `_` literally.
     *
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     */
    private static function like(
        Builder $query,
        string $column,
        string $value,
    ): void {
        $base = $query->getQuery();
        $driver = $query->getModel()->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $escaped = strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);

        // The column comes from the query declaration, never from the
        // request, and is wrapped by the grammar.
        $sql = $base->getGrammar()->wrap($column) . " {$operator} ? escape '!'";
        // @phpstan-ignore argument.type
        $base->whereRaw(new Expression($sql), ['%' . $escaped . '%']);
    }
}
