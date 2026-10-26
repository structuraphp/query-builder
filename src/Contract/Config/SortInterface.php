<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use StructuraPhp\QueryBuilder\Enum\Direction;

interface SortInterface
{
    public function sort(string $name): SortConfigInterface;

    public function default(
        string $name,
        Direction $direction = Direction::Asc,
    ): static;
}
