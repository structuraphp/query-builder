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
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;

final class CollectedQuery implements
    FromModel,
    WithFilter,
    WithScope,
    WithSort,
    WithRelation,
    WithPaginate
{
    public function __construct(
        private readonly string $rule,
        private readonly bool $flag,
        private readonly Direction $direction,
    ) {}

    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('views')->type(Type::Integer)->maxItems(3);
        // A comment ends the method.
    }

    public function scope(ScopeInterface $scope): void
    {
        $near = $scope->scope('near');
        $near->cast(lat: Type::Float, lng: static fn ($value) => $value);
        $scope->scope('ofStatuses')
            ->rules(statuses: ['max:2'])
            ->each([$this->rule]);
        $scope->scope('ofStatus')->cast(status: Status::class);
        $scope->scope('tapped')->default(callback: null);
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->default('date', Direction::Desc);
        $sort->default(name: 'date', direction: $this->direction);
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author')->fields(['id', 'name']);
        $relation->relation('comments')
            ->fields($this->flag ? ['id'] : ['body']);
        $relation->count('comments')->constrain(static fn ($query) => $query);
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Offset)->limit(10);
        $pagination->maxLimit($this->flag ? 20 : 30);
    }
}
