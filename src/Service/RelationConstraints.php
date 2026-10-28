<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Contracts\Database\Eloquent\SupportsPartialRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use StructuraPhp\QueryBuilder\ValueObject\Config\CountConfig;
use StructuraPhp\QueryBuilder\ValueObject\Config\RelationConfig;

/**
 * What Laravel needs to load or count a declared relation: the
 * fields() and the constrain() of its configuration, with the values the
 * code gave under the `with` and `count` keys of the context.
 */
final readonly class RelationConstraints
{
    /**
     * @param array<string, array<string, mixed>> $context only the `with`
     *                                                     and `count` keys
     *                                                     are read
     */
    public function __construct(
        private Definition $definition,
        private array $context = [],
    ) {
        $this->assertKeys(
            'with',
            'Relation',
            array_map(
                static fn (RelationConfig $config): ?Closure => $config
                    ->constraint,
                $definition->relations,
            ),
        );
        $this->assertKeys(
            'count',
            'Count',
            array_map(
                static fn (CountConfig $config): ?Closure => $config
                    ->constraint,
                $definition->counts,
            ),
        );
    }

    /**
     * @return Closure(Relation<*, *, *>): void|string
     */
    public function eagerLoad(string $path): Closure|string
    {
        $config = $this->definition->relations[$path];
        $fields = $config->fields;
        $constraint = $config->constraint;

        if ($constraint === null) {
            return $fields === []
                ? $path
                : $path . ':' . implode(',', $fields);
        }

        $values = $this->values('with', 'Relation', $path, $constraint);

        return static function (Relation $relation) use (
            $constraint,
            $fields,
            $values,
        ): void {
            $constraint($relation, ...$values);

            if ($fields === []) {
                return;
            }

            if (self::qualified($relation)) {
                $fields = array_map(
                    $relation->getRelated()->qualifyColumn(...),
                    $fields,
                );
            }

            // MorphTo replays what goes through __call() on each morphed type.
            $relation->__call('select', [$fields]);
        };
    }

    /**
     * @return null|Closure(Builder<Model>): void
     */
    public function count(string $name): ?Closure
    {
        $constraint = ($this->definition->counts[$name] ?? null)?->constraint;
        if ($constraint === null) {
            return null;
        }

        $values = $this->values('count', 'Count', $name, $constraint);

        return static function (Builder $query) use (
            $constraint,
            $values,
        ): void {
            $constraint($query, ...$values);
        };
    }

    /**
     * Context is checked even when its relation is not requested, so a typo
     * shows up at once.
     *
     * @param array<string, null|Closure> $constraints by name
     */
    private function assertKeys(
        string $key,
        string $label,
        array $constraints,
    ): void {
        foreach ($this->given($key) as $name => $values) {
            if (!\array_key_exists($name, $constraints)) {
                throw new InvalidArgumentException(
                    "{$label} {$name} is not declared.",
                );
            }

            $known = array_map(
                static fn (ReflectionParameter $parameter): string => $parameter
                    ->name,
                $this->parameters($constraints[$name]),
            );
            foreach (array_diff(array_keys($values), $known) as $unknown) {
                throw new InvalidArgumentException(
                    "{$label} {$name} has no context argument {$unknown}.",
                );
            }
        }
    }

    /**
     * Resolved when the query is built, not when Laravel runs the closure.
     *
     * @return array<string, mixed>
     */
    private function values(
        string $key,
        string $label,
        string $name,
        Closure $constraint,
    ): array {
        $given = $this->given($key)[$name] ?? [];

        $values = [];
        foreach ($this->parameters($constraint) as $parameter) {
            $argument = $parameter->name;
            if (!\array_key_exists($argument, $given)) {
                if (!$parameter->isOptional()) {
                    throw new LogicException(\sprintf(
                        'Missing context argument %s of %s %s.',
                        $argument,
                        strtolower($label),
                        $name,
                    ));
                }

                continue;
            }

            $values[$argument] = $this->resolve($parameter, $given[$argument]);
        }

        return $values;
    }

    /**
     * A closure is called, unless the parameter expects one.
     */
    private function resolve(
        ReflectionParameter $parameter,
        mixed $value,
    ): mixed {
        $type = $parameter->getType();
        $lazy = !\in_array(
            $type instanceof ReflectionNamedType ? $type->getName() : null,
            [Closure::class, 'callable'],
            true,
        );

        return $lazy && $value instanceof Closure ? $value() : $value;
    }

    /**
     * Laravel qualifies the columns the same way for `path:columns`.
     *
     * @param Relation<*, *, *> $relation
     */
    private static function qualified(Relation $relation): bool
    {
        return $relation instanceof BelongsToMany
            || $relation instanceof HasOneOrManyThrough
            || (
                $relation instanceof SupportsPartialRelations
                && $relation->isOneOfMany()
            );
    }

    /**
     * The context parameters of a constraint: all but the first.
     *
     * @return array<int, ReflectionParameter>
     */
    private function parameters(?Closure $constraint): array
    {
        if (!$constraint instanceof Closure) {
            return [];
        }

        $parameters = new ReflectionFunction($constraint)->getParameters();

        return array_filter(
            \array_slice($parameters, 1),
            static fn (ReflectionParameter $parameter): bool => !$parameter
                ->isVariadic(),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function given(string $key): array
    {
        /** @var array<string, array<string, mixed>> $given */
        $given = $this->context[$key] ?? [];

        return $given;
    }
}
