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

abstract class UnreadableQuery implements FromModel
{
    public function getModel(): string
    {
        return Article::class;
    }

    protected static function number(): int
    {
        return 0;
    }

    protected static function name(): string
    {
        return 'author';
    }

    protected static function mode(): PaginationMode
    {
        return PaginationMode::None;
    }
}

final class UnreadableQueryRequest extends UnreadableQuery implements
    WithFilter,
    WithSort,
    WithRelation,
    WithPaginate
{
    public function filter(FilterInterface $filter): void
    {
        $filter->filter('items')
            ->type(Type::String)
            ->maxItems(self::number())
            ->maxLength(self::number());
        $filter->filter(self::name())->type(Type::String);
        $filter->filter(self::name());
        $filter->filter('title')
            ->type(Type::String)
            ->maxLength(1)
            ->maxLength(self::number());
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort(self::name())->count('comments');
        $sort->sort('title')->count(self::name());
        $sort->default('anything');
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation(self::name());
        $relation->relation('author.posts');
        $relation->count(self::name());
        $relation->count(self::name());
        $relation->count('comments');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(self::number());
        $pagination->default(self::mode());
        $pagination->maxLimit(5);
    }
}

final class UnreadableAllowQuery extends UnreadableQuery implements
    WithPaginate
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(PaginationMode::None);
        $pagination->allow(PaginationMode::Offset, self::mode());
    }
}

final class OtherEnumModeQuery extends UnreadableQuery implements WithPaginate
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(Direction::Asc);
        $pagination->allow(Direction::Desc);
    }
}

final class DynamicVariableQuery extends UnreadableQuery implements WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        ${'a'} = $scope->scope('missing');
    }
}
