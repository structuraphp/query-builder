<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;

interface WithFilter
{
    public function filter(FilterInterface $filter): void;
}
