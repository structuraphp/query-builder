<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;

/**
 * Applies the requested scopes, with the arguments of the request and those
 * the code gives as context.
 */
final readonly class ScopeApplier
{
    /**
     * @param array<string, array<string, mixed>> $context scope arguments
     *                                                     given by the code;
     *                                                     `with` and `count`
     *                                                     are not read
     */
    public function __construct(
        private Definition $definition,
        private array $context = [],
    ) {
        $this->assertContext();
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
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input raw request data, only used to
     *                                    detect requested scopes
     */
    public function apply(Builder $query, array $validated, array $input): void
    {
        $scopes = $this->definition->scopes;
        foreach ($scopes as $name => [$config, $arguments, $onBuilder]) {
            $values = $this->values($name, $arguments, $validated, $input);
            if ($values === null) {
                continue;
            }

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
     * Context is checked even when its scope is not requested, so a typo
     * shows up at once.
     */
    private function assertContext(): void
    {
        foreach ($this->context as $scope => $values) {
            // Read by RelationConstraints.
            if ($scope === 'with' || $scope === 'count') {
                continue;
            }

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
     * @param list<ScopeArgument> $arguments
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input
     *
     * @return null|list<mixed> null when the scope is not applied
     */
    private function values(
        string $name,
        array $arguments,
        array $validated,
        array $input,
    ): ?array {
        $fromRequest = self::fromRequest($arguments);
        // validated() drops a scope whose sub-keys are all unknown or
        // omitted, yet it was still requested.
        $requested = \array_key_exists($name, $validated)
            || ($fromRequest !== [] && \is_array($input[$name] ?? null));
        if (!$requested) {
            return null;
        }

        $given = $validated[$name] ?? [];
        $enabled = filter_var($given, FILTER_VALIDATE_BOOLEAN);
        // A boolean, for a scope without arguments or with defaults only.
        if (!\is_array($given) && !$enabled) {
            return null;
        }

        $resolved = [];
        foreach ($fromRequest as $argument) {
            $resolved[$argument->name] = $this->argument($argument, $given);
        }

        $provided = $this->context[$name] ?? [];

        return array_map(
            fn (ScopeArgument $arg): mixed => $arg->context
                ? $this->context($name, $arg, $provided, $resolved)
                : $resolved[$arg->name],
            $arguments,
        );
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
}
