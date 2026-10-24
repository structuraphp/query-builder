<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\FilterConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;

final class Filter implements FilterInterface
{
    /** @var list<FilterConfig> */
    public private(set) array $filters = [];

    public function filter(string $name): FilterConfigInterface
    {
        return $this->filters[] = new FilterConfig($name);
    }
}
