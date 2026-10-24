<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface ScopeInterface
{
    public function scope(string $name): ScopeConfigInterface;
}
