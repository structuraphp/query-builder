<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface RelationConfigInterface
{
    /**
     * @param list<string> $fields
     */
    public function fields(array $fields): static;
}
