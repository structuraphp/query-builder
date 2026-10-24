<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use UnexpectedValueException;

enum Type
{
    case String;
    case Integer;
    case Float;
    case Boolean;
    case Date;

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::String => ['string'],
            self::Integer => ['integer'],
            self::Float => ['numeric'],
            self::Boolean => ['boolean'],
            self::Date => ['date_format:Y-m-d'],
        };
    }

    /**
     * @return list<Mode>
     */
    public function modes(): array
    {
        return match ($this) {
            self::String => [Mode::Equal, Mode::Like],
            self::Boolean => [Mode::Equal],
            self::Integer, self::Float, self::Date => [
                Mode::Equal,
                Mode::GreaterThan,
                Mode::LessThan,
                Mode::GreaterOrEqual,
                Mode::LessOrEqual,
            ],
        };
    }

    public function cast(mixed $value): mixed
    {
        $value = self::scalar($value);

        return match ($this) {
            self::String => (string) $value,
            self::Integer => (int) $value,
            self::Float => (float) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Date => CarbonImmutable::createFromFormat(
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

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     */
    public function apply(
        Builder $query,
        string $column,
        Mode $mode,
        mixed $value,
    ): void {
        $base = $query->getQuery();

        if ($mode === Mode::Like) {
            self::like($query, $column, (string) self::scalar($value));

            return;
        }

        if ($this === self::Date && $value instanceof CarbonImmutable) {
            foreach (self::day($mode, $value) as $operator => $bound) {
                $base->where($column, $operator, $bound);
            }

            return;
        }

        $base->where($column, $mode->value, $value);
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
            default => [$mode->value => $start],
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
