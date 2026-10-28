<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface ScopeInterface
{
    /**
     * @param literal-string $name
     */
    public function scope(string $name): ScopeConfigInterface;
}
