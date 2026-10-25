<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class FilterArticleQuery implements FromModel, WithFilter
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('title')->type(Type::String);
        $filter->filter('views')->type(Type::Integer);
        $filter->filter('statut')
            ->column('status')
            ->type(Type::String)
            ->rules(['in:draft,published'])
            ->messages(['in' => 'Statut invalide.'])
            ->attribute('statut');
        $filter->filter('published_at')->type(Type::Date)->nullable();
        $filter->filter('rating')->type(Type::Float);
        $filter->filter('featured')->type(Type::Boolean);
    }
}
