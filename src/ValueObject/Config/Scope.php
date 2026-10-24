<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;

final class Scope implements ScopeInterface
{
    /** @var list<ScopeConfig> */
    public private(set) array $scopes = [];

    public function scope(string $name): ScopeConfigInterface
    {
        return $this->scopes[] = new ScopeConfig($name);
    }
}
