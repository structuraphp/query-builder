<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

/**
 * The first parameter of a constrain() closure: the relation or builder.
 */
final readonly class QueryParameter implements ParameterReflection
{
    public function __construct(
        private Type $type,
    ) {}

    public function getName(): string
    {
        return 'query';
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function passedByReference(): PassedByReference
    {
        return PassedByReference::createNo();
    }

    public function isVariadic(): bool
    {
        return false;
    }

    public function getDefaultValue(): ?Type
    {
        return null;
    }
}
