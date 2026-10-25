<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface SortConfigInterface
{
    public function column(string $column): static;

    /**
     * Sorts on the number of related models instead of a column.
     */
    public function count(string $relation): static;
}
