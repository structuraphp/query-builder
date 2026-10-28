<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;

abstract class InheritedScopeQuery implements FromModel, WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');
    }
}
