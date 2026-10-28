<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\SortConfigInterface;

final class SortConfig implements SortConfigInterface
{
    public private(set) ?string $column = null;

    public private(set) ?string $count = null;

    public function __construct(
        public readonly string $name,
    ) {}

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function count(string $relation): static
    {
        $this->count = $relation;

        return $this;
    }
}
