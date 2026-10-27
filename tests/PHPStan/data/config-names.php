<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class NamesQuery implements FromModel, WithFilter, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('a.b')->type(Type::String);
        $filter->filter('page')->type(Type::String);
        $filter->filter('title')->type(Type::String);
        $filter->filter('title')->type(Type::String);
        $filter->filter('with.dot');
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('title')->method('popular');
        $scope->scope('popular');
        $scope->scope('limit')->method('popular');
    }
}
