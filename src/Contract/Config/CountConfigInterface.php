<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use Closure;

interface CountConfigInterface
{
    /**
     * Limits the rows counted. The closure receives the Eloquent builder of
     * the related model, then the values of the `count` context by
     * parameter name. The last call wins.
     */
    public function constrain(Closure $constraint): static;
}
