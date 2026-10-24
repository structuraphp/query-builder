<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface SortConfigInterface
{
    public function column(string $column): static;
}
