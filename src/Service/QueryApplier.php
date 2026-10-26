<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\Mode;
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
     * @param array<string, array<string, mixed>> $context scope arguments
     *                                                     given by the code
     *
     * @return Builder<Model>
     */
    public function apply(
        array $validated,
        array $input = [],
        array $context = [],
    ): Builder {
        $this->assertContext($context);

        $query = $this->definition->model->newQuery();

        $this->applyFilters($query, $validated);
        $this->applyScopes($query, $validated, $input, $context);
        $sorted = $this->applySorts($query, $validated);
        $this->applyRelations($query, $validated);
        $this->applyCounts($query, $validated, $sorted);

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public static function relationNames(string $with): array
    {
        $names = array_map(trim(...), explode(',', $with));

        return array_filter($names, static fn (
            string $name,
        ): bool => $name !== '');
    }

    /**
     * @param list<ScopeArgument> $arguments
     *
     * @return array<int, ScopeArgument>
     */
    public static function fromRequest(array $arguments): array
    {
        return array_filter(
            $arguments,
            static fn (ScopeArgument $argument): bool => !$argument->context,
        );
    }

    /**
     * Context is checked even when its scope is not requested, so a typo
     * shows up at once.
     *
     * @param array<string, array<string, mixed>> $context
     */
    private function assertContext(array $context): void
    {
        foreach ($context as $scope => $values) {
            $arguments = $this->definition->scopes[$scope][1] ?? null;
            if ($arguments === null) {
                throw new InvalidArgumentException(
                    "Scope {$scope} is not declared.",
                );
            }

            $names = array_map(
                static fn (ScopeArgument $argument): string => $argument->name,
                array_filter(
                    $arguments,
                    static fn (
                        ScopeArgument $argument,
                    ): bool => $argument->context,
                ),
            );
            foreach (array_diff(array_keys($values), $names) as $unknown) {
                throw new InvalidArgumentException(
                    "Scope {$scope} has no context argument {$unknown}.",
                );
            }
        }
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
            $value = match (true) {
                $mode === Mode::IsNull => null,
                $mode === Mode::In && \is_array($input['value']) => array_map(
                    static fn (
                        mixed $item,
                    ): mixed => TypeCaster::cast($type, $item),
                    $input['value'],
                ),
                default => TypeCaster::cast($type, $input['value']),
            };

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
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input
     * @param array<string, array<string, mixed>> $context
     */
    private function applyScopes(
        Builder $query,
        array $validated,
        array $input,
        array $context,
    ): void {
        $scopes = $this->definition->scopes;
        foreach ($scopes as $name => [$config, $arguments, $onBuilder]) {
            $fromRequest = self::fromRequest($arguments);
            // validated() drops a scope whose sub-keys are all unknown or
            // omitted, yet it was still requested.
            $requested = \array_key_exists($name, $validated)
                || ($fromRequest !== [] && \is_array($input[$name] ?? null));
            if (!$requested) {
                continue;
            }

            $given = $validated[$name] ?? [];
            $enabled = filter_var($given, FILTER_VALIDATE_BOOLEAN);
            // A boolean, for a scope without arguments or with defaults only.
            if (!\is_array($given) && !$enabled) {
                continue;
            }

            $resolved = [];
            foreach ($fromRequest as $argument) {
                $resolved[$argument->name] = $this->argument($argument, $given);
            }

            $provided = $context[$name] ?? [];
            $values = array_map(
                fn (ScopeArgument $arg): mixed => $arg->context
                    ? $this->context($name, $arg, $provided, $resolved)
                    : $resolved[$arg->name],
                $arguments,
            );

            $method = $config->method ?? $config->name;
            if ($onBuilder) {
                // Called as is, like Laravel does: unlike a model scope, its
                // wheres are not grouped.
                $reflection = new ReflectionMethod($query, $method);
                $reflection->invoke($query, ...$values);
            } else {
                $query->scopes([$method => $values]);
            }
        }
    }

    /**
     * @param array<string, mixed> $given
     * @param array<string, mixed> $resolved request arguments of the scope,
     *                                       cast and defaulted
     */
    private function context(
        string $scope,
        ScopeArgument $argument,
        array $given,
        array $resolved,
    ): mixed {
        $value = $this->argument($argument, $given);
        if (!$argument->lazy || !$value instanceof Closure) {
            return $value;
        }

        $arguments = [];
        $parameters = new ReflectionFunction($value)->getParameters();
        foreach ($parameters as $parameter) {
            $name = $parameter->name;
            if (\array_key_exists($name, $resolved)) {
                $arguments[$name] = $resolved[$name];
            } elseif (!$parameter->isOptional()) {
                throw new InvalidArgumentException(
                    "Scope {$scope} has no request argument {$name} for the"
                    . " context of {$argument->name}.",
                );
            }
        }

        return $value(...$arguments);
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
     * Requested counts and those a sort needs: withCount() counts a
     * relation named twice once.
     *
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     * @param list<string> $sorted
     */
    private function applyCounts(
        Builder $query,
        array $validated,
        array $sorted,
    ): void {
        $count = $validated['count'] ?? null;
        $requested = \is_string($count) ? self::relationNames($count) : [];
        $query->withCount([...$requested, ...$sorted]);
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
    private function applyRelations(Builder $query, array $validated): void
    {
        $with = $validated['with'] ?? null;
        if (!\is_string($with)) {
            return;
        }

        $relations = $this->definition->relations;
        $query->with(array_map(
            static function (string $name) use ($relations): string {
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
