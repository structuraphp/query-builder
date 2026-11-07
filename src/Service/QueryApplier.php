<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\Mode;
use StructuraPhp\QueryBuilder\Enum\Type;

final readonly class QueryApplier
{
    public function __construct(
        private Definition $definition,
    ) {}

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input raw request data, only used to
     *                                    detect requested scopes
     * @param array<string, array<string, mixed>> $context scope arguments
     *                                                     given by the code,
     *                                                     and under `with`
     *                                                     and `count` those
     *                                                     of the constraints
     *
     * @return Builder<Model>
     */
    public function apply(
        array $validated,
        array $input = [],
        array $context = [],
    ): Builder {
        $scopes = new ScopeApplier($this->definition, $context);
        $constraints = new RelationConstraints($this->definition, $context);

        $query = $this->definition->model->newQuery();

        $this->applyFilters($query, $validated);
        $scopes->apply($query, $validated, $input);
        $sorted = $this->applySorts($query, $validated);
        $this->applyRelations($query, $validated, $constraints);
        $this->applyCounts($query, $validated, $sorted, $constraints);

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public static function relationNames(string $with): array
    {
        $names = array_map(trim(...), explode(',', $with));

        return array_filter(
            $names,
            static fn (string $name): bool => $name !== '',
        );
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     */
    private function applyFilters(Builder $query, array $validated): void
    {
        foreach ($this->definition->filters as $name => $config) {
            $input = $validated[$name] ?? null;
            $type = $config->type;
            if (!\is_array($input) || $type === null) {
                continue;
            }

            $column = $this->column($config->column ?? $name);
            $mode = Mode::from(
                (string) TypeCaster::scalar(
                    $input['mode'] ?? Mode::Equal->value,
                ),
            );
            $value = $this->filterValue($type, $mode, $input);

            if (filter_var($input['not'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNot(static fn (Builder $q) => FilterApplier::apply(
                    $q,
                    $type,
                    $column,
                    $mode,
                    $value,
                ));
            } else {
                FilterApplier::apply($query, $type, $column, $mode, $value);
            }
        }
    }

    /**
     * @param array<mixed> $input
     */
    private function filterValue(
        Type $type,
        Mode $mode,
        array $input,
    ): mixed {
        return match (true) {
            $mode === Mode::IsNull => null,
            $mode === Mode::In && \is_array($input['value']) => array_map(
                static fn (
                    mixed $item,
                ): mixed => TypeCaster::cast($type, $item),
                $input['value'],
            ),
            default => TypeCaster::cast($type, $input['value']),
        };
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     *
     * @return list<string> relations counted by the applied sorts
     */
    private function applySorts(Builder $query, array $validated): array
    {
        $counted = [];
        $sorts = $validated['sort'] ?? null;

        $pairs = \is_array($sorts)
            ? array_map(
                static fn (int|string $name, mixed $direction): array => [
                    $name,
                    Direction::from((string) TypeCaster::scalar($direction)),
                ],
                array_keys($sorts),
                $sorts,
            )
            : $this->definition->defaultSorts;

        foreach ($pairs as [$name, $direction]) {
            $config = $this->definition->sorts[$name] ?? null;
            if ($config === null) {
                continue;
            }

            if ($config->count !== null) {
                $counted[] = $config->count;
            }

            $query->getQuery()->orderBy(
                $config->count !== null
                    ? $this->countAlias($config->count)
                    : $this->column($config->column ?? $config->name),
                $direction->value,
            );
        }

        return $counted;
    }

    /**
     * Requested counts and those a sort needs, each once.
     *
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     * @param list<string> $sorted
     */
    private function applyCounts(
        Builder $query,
        array $validated,
        array $sorted,
        RelationConstraints $constraints,
    ): void {
        $count = $validated['count'] ?? null;
        $requested = \is_string($count) ? self::relationNames($count) : [];

        // Laravel nests a closure per repeated relation: thousands of
        // repeats overflow the stack.
        $counts = [];
        foreach (array_unique([...$requested, ...$sorted]) as $name) {
            $constraint = $constraints->count($name);
            if ($constraint instanceof Closure) {
                $counts[$name] = $constraint;
            } else {
                $counts[] = $name;
            }
        }

        $query->withCount($counts);
    }

    /**
     * The alias withCount() gives the count of a relation with a valid name.
     */
    private function countAlias(string $relation): string
    {
        return Str::snake($relation) . '_count';
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     */
    private function applyRelations(
        Builder $query,
        array $validated,
        RelationConstraints $constraints,
    ): void {
        $with = $validated['with'] ?? null;
        if (!\is_string($with)) {
            return;
        }

        $relations = [];
        foreach ($this->loads($with, $constraints) as $path => $load) {
            if ($load instanceof Closure) {
                $relations[$path] = $load;
            } else {
                $relations[] = $load;
            }
        }

        $query->with($relations);
    }

    /**
     * Laravel loads the parents of a nested relation without their fields():
     * name them too, so their columns stay limited.
     *
     * @return array<string, Closure|string> by path
     */
    private function loads(
        string $with,
        RelationConstraints $constraints,
    ): array {
        $loads = [];
        foreach (self::relationNames($with) as $name) {
            $path = null;
            foreach (explode('.', $name) as $segment) {
                $path = $path === null ? $segment : "{$path}.{$segment}";
                $loads[$path] ??= $constraints->eagerLoad($path);
            }
        }

        return $loads;
    }

    private function column(string $column): string
    {
        return $this->definition->model->qualifyColumn($column);
    }
}
