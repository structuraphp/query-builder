<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
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

    private const array SCALARS = ['int', 'float', 'bool', 'string', 'array'];

    /** Types that are not a class and that no cast-less argument has. */
    private const array PSEUDO = [
        'mixed',
        'callable',
        'iterable',
        'object',
        'null',
        'false',
        'true',
    ];

    /** @var ReflectionClass<*> */
    private ReflectionClass $model;

    /** @var null|ReflectionClass<*> null when it cannot be known */
    private ?ReflectionClass $builder;

    /**
     * @param class-string $model
     */
    public function __construct(
        private ReflectionProvider $reflection,
        string $model,
    ) {
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
     * The class of the model's Eloquent builder, null when it is unknown.
     */
    public function builderClass(): ?string
    {
        return $this->builder?->getName();
    }

    /**
     * Like at runtime, a method of the builder wins over a model scope.
     */
    public function findScope(string $name): ?ScopeSignature
    {
        if (!$this->builder instanceof ReflectionClass) {
            return null;
        }

        return self::builderScope($this->builder, $name)
            ?? $this->modelScope($name);
    }

    /**
     * Whether a class is a backed enum; null when it is unknown.
     */
    public function isBackedEnum(string $class): ?bool
    {
        return self::existing($this->reflection, $class)?->isBackedEnum();
    }

    /**
     * Whether ScopeReflector::fromType() takes a parameter of this type
     * without a cast: a scalar, an array, a backed enum or a date class.
     * Null when the type is a class that is not known.
     */
    public function acceptsWithoutCast(string $type): ?bool
    {
        if (\in_array($type, self::SCALARS, true)) {
            return true;
        }

        if (\in_array($type, self::PSEUDO, true)) {
            return false;
        }

        $class = self::existing($this->reflection, $type);

        if (!$class instanceof ClassReflection) {
            return null;
        }

        // A class Carbon or CarbonImmutable is, or inherits from.
        return $class->isBackedEnum()
            || $this->reflection->getClass(Carbon::class)
                ->is($class->getName())
            || $this->reflection->getClass(CarbonImmutable::class)
                ->is($class->getName());
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
        $hidden = $this->model->getProperty('hidden')->getDefaultValue();
        $columns = self::attribute($this->model, Hidden::class);

        // Like the attribute: one array of columns, or the columns.
        $columns = \is_array($columns[0] ?? null) ? $columns[0] : $columns;

        return array_values(array_filter(
            [...(\is_array($hidden) ? $hidden : []), ...$columns],
            \is_string(...),
        ));
    }

    /**
     * @param ReflectionClass<*> $builder
     */
    private static function builderScope(
        ReflectionClass $builder,
        string $name,
    ): ?ScopeSignature {
        if (!$builder->hasMethod($name)) {
            return null;
        }

        $method = $builder->getMethod($name);
        $custom = $method->getDeclaringClass()->isSubclassOf(Builder::class);

        return $custom && $method->isPublic() && !$method->isStatic()
            ? self::signature($method, true)
            : null;
    }

    private function modelScope(string $name): ?ScopeSignature
    {
        if ($this->model->hasMethod('scope' . $name)) {
            return self::signature(
                $this->model->getMethod('scope' . $name),
                false,
            );
        }

        if (!$this->model->hasMethod($name)) {
            return null;
        }

        $method = $this->model->getMethod($name);

        return !$method->isPrivate()
            && $method->getAttributes(Scope::class) !== []
                ? self::signature($method, false)
                : null;
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

        return new ScopeSignature(array_map(
            static fn (ReflectionParameter $parameter): array => [
                'name' => $parameter->getName(),
                'type' => $parameter->getType() instanceof ReflectionNamedType
                    ? $parameter->getType()->getName()
                    : null,
            ],
            $parameters,
        ));
    }
}
