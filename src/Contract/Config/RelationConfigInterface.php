<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface RelationConfigInterface
{
    /**
     * @param list<literal-string> $fields
     */
    public function fields(array $fields): static;
}
