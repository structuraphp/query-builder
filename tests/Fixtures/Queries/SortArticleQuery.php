<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class SortArticleQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->sort('title');
        $sort->default('date', 'desc')->default('title', 'asc');
    }
}
