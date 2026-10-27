<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;

final class PostQuery implements FromModel, WithScope, WithSort
{
    public function getModel(): string
    {
        return Post::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('published');
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('title');
        $sort->default('title');
    }
}
