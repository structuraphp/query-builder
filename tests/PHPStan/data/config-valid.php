<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class ValidQuery implements
    FromModel,
    WithFilter,
    WithScope,
    WithSort,
    WithRelation,
    WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('title')->type(Type::String)->maxItems(1);
        $filter->filter('Views_2')->type(Type::Integer)->maxLength(1);
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');
        $scope->scope('status')->method('ofStatus');
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('title');
        $sort->sort('date')->column('created_at');
        $sort->sort('comments')->count('comments');
        $sort->default('title');
        $sort->default('date', Direction::Desc);
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author');
        $relation->relation('comments');
        $relation->relation('comments.author');
        $relation->count('comments');
        $relation->count('author');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination
            ->allow(PaginationMode::Offset, PaginationMode::None)
            ->default(PaginationMode::None)
            ->limit(100)
            ->maxLimit(100);
    }
}
