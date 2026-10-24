<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use Closure;
use Illuminate\Database\Eloquent\Model;
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
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

/**
 * Every section is configured by a closure, so a test declares only what it
 * needs.
 */
final readonly class ConfigurableQuery implements
    FromModel,
    WithFilter,
    WithPaginate,
    WithRelation,
    WithScope,
    WithSort
{
    /**
     * @param null|(Closure(FilterInterface): void) $filter
     * @param null|(Closure(PaginationInterface): void) $pagination
     * @param null|(Closure(RelationInterface): void) $relation
     * @param null|(Closure(ScopeInterface): void) $scope
     * @param null|(Closure(SortInterface): void) $sort
     * @param class-string<Model> $model
     */
    public function __construct(
        private ?Closure $filter = null,
        private ?Closure $pagination = null,
        private ?Closure $relation = null,
        private ?Closure $scope = null,
        private ?Closure $sort = null,
        private string $model = Article::class,
    ) {}

    public function getModel(): string
    {
        return $this->model;
    }

    public function filter(FilterInterface $filter): void
    {
        $this->filter?->__invoke($filter);
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $this->pagination?->__invoke($pagination);
    }

    public function relation(RelationInterface $relation): void
    {
        $this->relation?->__invoke($relation);
    }

    public function scope(ScopeInterface $scope): void
    {
        $this->scope?->__invoke($scope);
    }

    public function sort(SortInterface $sort): void
    {
        $this->sort?->__invoke($sort);
    }
}
