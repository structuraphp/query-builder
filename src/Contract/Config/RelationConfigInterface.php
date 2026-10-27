<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use Closure;

interface RelationConfigInterface
{
    /**
     * @param list<literal-string> $fields
     */
    public function fields(array $fields): static;

    /**
     * Limits the rows loaded with the relation. The closure receives the
     * relation Laravel loads (`HasMany`, `BelongsTo`...), then the values of
     * the `with` context by parameter name. The last call wins.
     */
    public function constrain(Closure $constraint): static;
}
