<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class CountArticleQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author');
        $relation->relation('author.articles');
        $relation->count('comments');
    }
}
