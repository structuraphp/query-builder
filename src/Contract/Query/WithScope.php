<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;

interface WithScope
{
    public function scope(ScopeInterface $scope): void;
}
