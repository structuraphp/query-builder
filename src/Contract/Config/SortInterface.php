<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface SortInterface
{
    public function sort(string $name): SortConfigInterface;

    public function default(string $name, string $direction = 'asc'): static;
}
