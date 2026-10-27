<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Reads a model and its builder like Laravel and ScopeReflector do at
 * runtime, through PHPStan's reflection only.
 */
final readonly class ModelInspector
{
    /** Model methods that make the builder unknown once overridden. */
    private const array BUILDING = [
        'resolveCustomBuilderClass',
        'newModelQuery',
        'newQuery',
    ];

    /** @var ReflectionClass<*> */
    private ReflectionClass $model;

    /** @var null|ReflectionClass<*> null when it cannot be known */
    private ?ReflectionClass $builder;

    /**
     * @param class-string $model
     */
    public function __construct(ReflectionProvider $reflection, string $model)
    {
        $class = $reflection->getClass($model);
        $this->model = $class->getNativeReflection();
        $this->builder = self::builder($reflection, $class);
    }

    /**
     * Whether a scope findScope() does not find is certainly missing.
     */
    public function knowsScopes(): bool
    {
        return $this->builder instanceof ReflectionClass;
    }

    /**
     * Like at runtime, a method of the builder wins over a model scope.
     */
    public function findScope(string $name): ?ScopeSignature
    {
        if (!$this->builder instanceof ReflectionClass) {
            return null;
        }

        if ($this->builder->hasMethod($name)) {
            $method = $this->builder->getMethod($name);
            $custom = $method->getDeclaringClass()
                ->isSubclassOf(Builder::class);

            if ($custom && $method->isPublic() && !$method->isStatic()) {
                return self::signature($method, true);
            }
        }

        if ($this->model->hasMethod('scope' . $name)) {
            return self::signature(
                $this->model->getMethod('scope' . $name),
                false,
            );
        }

        if ($this->model->hasMethod($name)) {
            $method = $this->model->getMethod($name);

            if (
                !$method->isPrivate()
                && $method->getAttributes(Scope::class) !== []
            ) {
                return self::signature($method, false);
            }
        }

        return null;
    }

    public function name(): string
    {
        return $this->model->getName();
    }

    /**
     * Like at runtime, any method of the model: pass the root of a nested
     * relation.
     */
    public function hasRelation(string $name): bool
    {
        return $this->model->hasMethod($name);
    }

    /**
     * The default value of `$hidden` and the `#[Hidden]` attribute: columns
     * the model hides at runtime only are not known.
     *
     * @return list<string>
     */
    public function hiddenColumns(): array
    {
        /** @var list<string> $hidden */
        $hidden = $this->model->getProperty('hidden')->getDefaultValue();
        $columns = self::attribute($this->model, Hidden::class);

        // Like the attribute: one array of columns, or the columns.
        /** @var list<string> $columns */
        $columns = \is_array($columns[0] ?? null) ? $columns[0] : $columns;

        return [...$hidden, ...$columns];
    }

    /**
     * @return null|ReflectionClass<*>
     */
    private static function builder(
        ReflectionProvider $reflection,
        ClassReflection $model,
    ): ?ReflectionClass {
        $overrides = static fn (string $method): bool => $model
            ->getNativeMethod($method)
            ->getDeclaringClass()
            ->getName() !== Model::class;

        // They may build the query some other way.
        foreach (self::BUILDING as $method) {
            if ($overrides($method)) {
                return null;
            }
        }

        if ($overrides('newEloquentBuilder')) {
            $classes = $model->getNativeMethod('newEloquentBuilder')
                ->getOnlyVariant()
                ->getReturnType()
                ->getObjectClassReflections();

            // Unless final, the builder may be a subclass with more methods.
            return \count($classes) === 1
                && $classes[0]->isFinal()
                && $classes[0]->isSubclassOf(Builder::class)
                ? $classes[0]->getNativeReflection()
                : null;
        }

        $native = $model->getNativeReflection();
        $attribute = self::existing(
            $reflection,
            array_values(
                self::attribute($native, UseEloquentBuilder::class),
            )[0] ?? null,
        );

        // Like Model::newEloquentBuilder(): a builder, else $builder.
        if ($attribute?->isSubclassOf(Builder::class) === true) {
            return $attribute->getNativeReflection();
        }

        return self::existing(
            $reflection,
            $native->getProperty('builder')->getDefaultValue(),
        )?->getNativeReflection();
    }

    private static function existing(
        ReflectionProvider $reflection,
        mixed $class,
    ): ?ClassReflection {
        return \is_string($class) && $reflection->hasClass($class)
            ? $reflection->getClass($class)
            : null;
    }

    /**
     * The arguments of the first attribute found on the class, its traits,
     * then its parents, like Model::resolveClassAttribute().
     *
     * @param ReflectionClass<*> $class
     *
     * @return array<int|string, mixed>
     */
    private static function attribute(
        ReflectionClass $class,
        string $attribute,
    ): array {
        do {
            foreach ([$class, ...$class->getTraits()] as $source) {
                $found = $source->getAttributes($attribute);

                if ($found !== []) {
                    return $found[0]->getArguments();
                }
            }

            $class = $class->getParentClass();
        } while ($class instanceof ReflectionClass);

        return [];
    }

    private static function signature(
        ReflectionMethod $method,
        bool $onBuilder,
    ): ScopeSignature {
        // A model scope receives the query first.
        $parameters = \array_slice(
            $method->getParameters(),
            $onBuilder ? 0 : 1,
        );

        return new ScopeSignature($onBuilder, array_map(
            static fn (ReflectionParameter $parameter): array => [
                'name' => $parameter->getName(),
                'type' => $parameter->getType() instanceof ReflectionNamedType
                    ? $parameter->getType()->getName()
                    : null,
                'optional' => $parameter->isDefaultValueAvailable(),
            ],
            $parameters,
        ));
    }
}
