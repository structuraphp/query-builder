<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use Closure;
use StructuraPhp\QueryBuilder\Contract\Config\CountConfigInterface;

final class CountConfig implements CountConfigInterface
{
    public private(set) ?Closure $constraint = null;

    public function __construct(
        public readonly string $name,
    ) {}

    public function constrain(Closure $constraint): static
    {
        $this->constraint = $constraint;

        return $this;
    }
}
