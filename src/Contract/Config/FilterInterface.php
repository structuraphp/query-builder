<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface FilterInterface
{
    /**
     * @param literal-string $name
     */
    public function filter(string $name): FilterConfigInterface;
}
