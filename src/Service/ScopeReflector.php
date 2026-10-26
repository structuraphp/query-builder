<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope as ScopeAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\ValueObject\Config\ScopeConfig;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;

/**
 * @phpstan-type Resolved array{list<mixed>, Closure(mixed): mixed}
 */
final class ScopeReflector
{
    /**
     * @return list<ScopeArgument>
     */
    public function arguments(Model $model, ScopeConfig $config): array
    {
        $method = $this->method($model, $config);
        // A model scope receives the query first, a builder method does not.
        $offset = $this->onBuilder($model, $config) ? 0 : 1;
        $parameters = \array_slice($method->getParameters(), $offset);
        $names = array_map(
            fn (ReflectionParameter $parameter): string => $parameter->name,
            $parameters,
        );

        $options = [
            $config->context,
            ...array_map(array_keys(...), [
                $config->casts,
                $config->defaults,
                $config->rules,
                $config->each,
                $config->nested,
                $config->messages,
                $config->attributes,
                $config->nestedAttributes,
            ]),
        ];
        foreach ($options as $option) {
            foreach (array_diff($option, $names) as $unknown) {
                throw new ConfigurationException(\sprintf(
                    'Scope "%s" has no argument "%s".',
                    $config->name,
                    $unknown,
                ));
            }
        }

        return array_map(
            fn (ReflectionParameter $parameter): ScopeArgument => $this
                ->argument($config, $parameter),
            $parameters,
        );
    }

    /**
     * Like Laravel, a method of the model's own builder wins over a model
     * scope of the same name.
     */
    public function onBuilder(Model $model, ScopeConfig $config): bool
    {
        $method = $this->builderMethod($model, $config);

        return $method instanceof ReflectionMethod;
    }

    /**
     * Only a public method declared by a custom builder: Eloquent's own
     * methods (`delete()`…) are never scopes.
     */
    private function builderMethod(
        Model $model,
        ScopeConfig $config,
    ): ?ReflectionMethod {
        $method = $config->method ?? $config->name;
        $builder = $model->newModelQuery();
        if (!method_exists($builder, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($builder, $method);
        $declaring = $reflection->getDeclaringClass();
        $custom = $declaring->isSubclassOf(Builder::class);

        return $custom && $reflection->isPublic() && !$reflection->isStatic()
            ? $reflection
            : null;
    }

    private function method(
        Model $model,
        ScopeConfig $config,
    ): ReflectionMethod {
        $builderMethod = $this->builderMethod($model, $config);
        if ($builderMethod instanceof ReflectionMethod) {
            return $builderMethod;
        }

        $method = $config->method ?? $config->name;
        // Method names are case-insensitive: no ucfirst() needed.
        $prefixed = 'scope' . $method;

        if (method_exists($model, $prefixed)) {
            return new ReflectionMethod($model, $prefixed);
        }

        if (method_exists($model, $method)) {
            $reflection = new ReflectionMethod($model, $method);
            $attributes = $reflection->getAttributes(ScopeAttribute::class);
            if (!$reflection->isPrivate() && $attributes !== []) {
                return $reflection;
            }
        }

        throw new ConfigurationException(\sprintf(
            'Scope "%s" does not exist on %s.',
            $method,
            $model::class,
        ));
    }

    private function argument(
        ScopeConfig $config,
        ReflectionParameter $parameter,
    ): ScopeArgument {
        $name = $parameter->name;

        if (\in_array($name, $config->context, true)) {
            return $this->contextArgument($config, $parameter);
        }

        [$rules, $cast] = isset($config->casts[$name])
            ? $this->fromCast($config, $name, $config->casts[$name])
            : $this->fromType($config, $parameter);

        $each = $config->each[$name] ?? [];
        $nested = $config->nested[$name] ?? [];
        $flat = $each === []
            && $nested === []
            && !isset($config->casts[$name])
            && $this->typeName($parameter) === 'array';

        return new ScopeArgument(
            name: $name,
            rules: [...$rules, ...$config->rules[$name] ?? []],
            each: $flat ? [$this->flat()] : $each,
            messages: $config->messages[$name] ?? [],
            attribute: $config->attributes[$name] ?? null,
            cast: $cast,
            default: $this->default($config, $parameter),
            nested: $nested,
            check: ($config->casts[$name] ?? null) instanceof Closure
                ? $cast
                : null,
            nestedAttributes: $config->nestedAttributes[$name] ?? [],
        );
    }

    /**
     * Without each() or nested(), an array argument is a flat list: a nested
     * array would reach the scope unchecked.
     */
    private function flat(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var Closure(string): PotentiallyTranslatedString $fail */
            if (\is_array($value)) {
                $fail('validation.in')->translate();
            }
        };
    }

    /**
     * The code gives the value as is, or a closure resolved only when the
     * scope is applied (see `lazy`), unless the scope itself expects a
     * closure.
     */
    private function contextArgument(
        ScopeConfig $config,
        ReflectionParameter $parameter,
    ): ScopeArgument {
        $name = $parameter->name;
        $options = [
            'cast' => $config->casts,
            'rules' => $config->rules,
            'each' => $config->each,
            'nested' => $config->nested,
            'messages' => $config->messages,
            'attribute' => [
                ...$config->attributes,
                ...$config->nestedAttributes,
            ],
        ];
        foreach ($options as $option => $values) {
            if (\array_key_exists($name, $values)) {
                throw new ConfigurationException(\sprintf(
                    'Scope "%s" argument "%s" comes from the context: it'
                    . ' takes no %s().',
                    $config->name,
                    $name,
                    $option,
                ));
            }
        }

        $type = $this->typeName($parameter);

        return new ScopeArgument(
            name: $name,
            rules: [],
            each: [],
            messages: [],
            attribute: null,
            cast: fn (mixed $value): mixed => $value,
            default: $this->default($config, $parameter),
            context: true,
            lazy: !\in_array($type, [Closure::class, 'callable'], true),
        );
    }

    /**
     * @param class-string<BackedEnum>|Closure|Type $cast
     *
     * @return Resolved
     */
    private function fromCast(
        ScopeConfig $config,
        string $name,
        Closure|string|Type $cast,
    ): array {
        if ($cast instanceof Type) {
            return [$cast->rules(), $cast->cast(...)];
        }

        if ($cast instanceof Closure) {
            return $this->closure($cast);
        }

        if (is_subclass_of($cast, BackedEnum::class)) {
            return $this->enum($cast);
        }

        throw new ConfigurationException(\sprintf(
            'Scope "%s" argument "%s" cast must be a Type, a backed enum'
            . ' or a closure.',
            $config->name,
            $name,
        ));
    }

    /**
     * Validation and application share the result, so the closure runs once
     * per value and request.
     *
     * @param Closure $cast any closure, typed by the user
     *
     * @return Resolved
     */
    private function closure(Closure $cast): array
    {
        $results = [];
        $memoized = function (mixed $value) use ($cast, &$results): mixed {
            $key = serialize($value);
            if (!\array_key_exists($key, $results)) {
                $results[$key] = $cast($value);
            }

            return $results[$key];
        };

        return [[], $memoized];
    }

    /**
     * @return Resolved
     */
    private function fromType(
        ScopeConfig $config,
        ReflectionParameter $parameter,
    ): array {
        $class = $this->typeName($parameter);

        $builtin = match ($class) {
            'int' => Type::Integer,
            'float' => Type::Float,
            'bool' => Type::Boolean,
            'string' => Type::String,
            default => null,
        };

        if ($builtin !== null) {
            return [$builtin->rules(), $builtin->cast(...)];
        }

        if ($class === 'array') {
            return [['array'], fn (mixed $value): mixed => $value];
        }

        if ($class !== null && is_subclass_of($class, BackedEnum::class)) {
            return $this->enum($class);
        }

        if ($class !== null && is_a(CarbonImmutable::class, $class, true)) {
            return [Type::Date->rules(), Type::Date->cast(...)];
        }

        if ($class !== null && is_a(Carbon::class, $class, true)) {
            return [
                Type::Date->rules(),
                fn (mixed $value): ?Carbon => Carbon::createFromFormat(
                    '!Y-m-d',
                    (string) Type::scalar($value),
                ),
            ];
        }

        throw new ConfigurationException(\sprintf(
            'Scope "%s" argument "%s" needs a scalar, enum, date or array'
            . ' type, or an explicit cast().',
            $config->name,
            $parameter->name,
        ));
    }

    /**
     * @param class-string<BackedEnum> $enum
     *
     * @return Resolved
     */
    private function enum(string $enum): array
    {
        $backing = (string) new ReflectionEnum($enum)->getBackingType();
        $cast = static function (mixed $value) use ($enum, $backing) {
            $value = Type::scalar($value);

            return $enum::from(
                $backing === 'int' ? (int) $value : (string) $value,
            );
        };

        return [[Rule::enum($enum)], $cast];
    }

    /**
     * `now()` returns a mutable Carbon: match the date class the scope
     * expects.
     */
    private function coerceDate(
        ReflectionParameter $parameter,
        mixed $value,
    ): mixed {
        $class = $this->typeName($parameter);

        if (
            !$value instanceof DateTimeInterface
            || $class === null
            || $value instanceof $class
        ) {
            return $value;
        }

        if (is_a(CarbonImmutable::class, $class, true)) {
            return CarbonImmutable::instance($value);
        }

        return is_a(Carbon::class, $class, true)
            ? Carbon::instance($value)
            : $value;
    }

    /**
     * @return null|(Closure(): mixed)
     */
    private function default(
        ScopeConfig $config,
        ReflectionParameter $parameter,
    ): ?Closure {
        $name = $parameter->name;

        if (\array_key_exists($name, $config->defaults)) {
            $default = $config->defaults[$name];
            $resolve = $default instanceof Closure
                ? $default
                : fn (): mixed => $default;

            return fn (): mixed => $this->coerceDate($parameter, $resolve());
        }

        if ($parameter->isDefaultValueAvailable()) {
            $value = $parameter->getDefaultValue();

            return fn (): mixed => $value;
        }

        return null;
    }

    private function typeName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }
}
