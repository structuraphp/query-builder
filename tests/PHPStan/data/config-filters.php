<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class FiltersQuery implements FromModel, WithFilter
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('untyped');
        $filter->filter('items')
            ->type(Type::String)
            ->maxItems(0);
        $filter->filter('length')->type(Type::String)->maxLength(-1);
        $filter->filter('both')->maxItems(0)->maxLength(0);
        $filter->filter('fine')->type(Type::String)->maxItems(0)->maxItems(2);
        $filter->filter('edge')->type(Type::String)->maxItems(1)->maxLength(1);
        $filter->filter('named')->type(Type::String)->maxLength(max: 0);
    }
}
