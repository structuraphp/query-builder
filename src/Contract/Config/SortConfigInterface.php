<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface SortConfigInterface
{
    /**
     * @param literal-string $column
     */
    public function column(string $column): static;

    /**
     * Sorts on the number of related models instead of a column.
     *
     * @param literal-string $relation
     */
    public function count(string $relation): static;
}
