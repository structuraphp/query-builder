<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use StructuraPhp\QueryBuilder\Enum\Direction;

interface SortInterface
{
    /**
     * @param literal-string $name
     */
    public function sort(string $name): SortConfigInterface;

    /**
     * @param literal-string $name
     */
    public function default(
        string $name,
        Direction $direction = Direction::Asc,
    ): static;
}
