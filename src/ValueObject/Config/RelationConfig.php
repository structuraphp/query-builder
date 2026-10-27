<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use Closure;
use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;

final class RelationConfig implements RelationConfigInterface
{
    /** @var list<string> */
    public private(set) array $fields = [];

    public private(set) ?Closure $constraint = null;

    public function __construct(
        public readonly string $name,
    ) {}

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function constrain(Closure $constraint): static
    {
        $this->constraint = $constraint;

        return $this;
    }
}
