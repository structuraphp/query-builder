<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;

interface WithSort
{
    public function sort(SortInterface $sort): void;
}
