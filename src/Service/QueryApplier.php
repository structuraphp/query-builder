<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use StructuraPhp\QueryBuilder\Enum\Mode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;

final readonly class QueryApplier
{
    public function __construct(
        private Definition $definition,
    ) {}

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input raw request data, only used to
     *                                    detect requested scopes
     *
     * @return Builder<Model>
     */
    public function apply(array $validated, array $input = []): Builder
    {
        $query = $this->definition->model->newQuery();

        $this->applyFilters($query, $validated);
        $this->applyScopes($query, $validated, $input);
        $this->applySorts($query, $validated);
        $this->applyRelations($query, $validated);

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function relationNames(string $with): array
    {
        $names = array_map(trim(...), explode(',', $with));

        return array_values(array_filter(
            $names,
            fn (string $name): bool => $name !== '',
        ));
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
                (string) Type::scalar($input['mode'] ?? Mode::Equal->value),
            );
            $value = $type->cast($input['value']);

            if (filter_var($input['not'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNot(
                    fn (Builder $q) => $type->apply($q, $column, $mode, $value),
                );
            } else {
                $type->apply($query, $column, $mode, $value);
            }
        }
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input
     */
    private function applyScopes(
        Builder $query,
        array $validated,
        array $input,
    ): void {
        $scopes = $this->definition->scopes;
        foreach ($scopes as $name => [$config, $arguments]) {
            // validated() drops a scope whose sub-keys are all unknown or
            // omitted, yet it was still requested.
            $requested = \array_key_exists($name, $validated)
                || ($arguments !== [] && \is_array($input[$name] ?? null));
            if (!$requested) {
                continue;
            }

            $given = $validated[$name] ?? [];
            $enabled = filter_var($given, FILTER_VALIDATE_BOOLEAN);
            if ($arguments === [] && !$enabled) {
                continue;
            }

            $values = array_map(
                fn (ScopeArgument $arg): mixed => $this->argument($arg, $given),
                $arguments,
            );

            $query->scopes([$config->method ?? $config->name => $values]);
        }
    }

    private function argument(
        ScopeArgument $argument,
        mixed $given,
    ): mixed {
        if (\is_array($given) && \array_key_exists($argument->name, $given)) {
            return ($argument->cast)($given[$argument->name]);
        }

        if (!$argument->default instanceof Closure) {
            throw new LogicException(
                "Missing required scope argument {$argument->name}.",
            );
        }

        return ($argument->default)();
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     */
    private function applySorts(Builder $query, array $validated): void
    {
        $sorts = $validated['sort'] ?? null;
        $pairs = \is_array($sorts)
            ? array_map(null, array_keys($sorts), array_values($sorts))
            : $this->definition->defaultSorts;

        foreach ($pairs as [$name, $direction]) {
            $config = $this->definition->sorts[(string) $name] ?? null;
            if ($config === null) {
                continue;
            }

            $query->getQuery()->orderBy(
                $this->column($config->column ?? $config->name),
                $direction === 'desc' ? 'desc' : 'asc',
            );
        }
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     */
    private function applyRelations(Builder $query, array $validated): void
    {
        $with = $validated['with'] ?? null;
        if (!\is_string($with)) {
            return;
        }

        $relations = $this->definition->relations;
        $query->with(array_map(
            function (string $name) use ($relations): string {
                $fields = $relations[$name]->fields;

                return $fields === []
                    ? $name
                    : $name . ':' . implode(',', $fields);
            },
            self::relationNames($with),
        ));
    }

    private function column(string $column): string
    {
        return $this->definition->model->qualifyColumn($column);
    }
}
