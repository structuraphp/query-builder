<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class RelationsQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('ghost');
        $relation->relation('author.bad name');
        $relation->relation('comments');
        $relation->relation('comments');
        $relation->relation('author.posts.title');
        $relation->relation('comments.author');
        $relation->relation('author.posts');
        $relation->relation('author.posts');
        $relation->count('comments');
        $relation->count('comments');
        $relation->count('comments.author');
        $relation->count('comments.author');
        $relation->count('ghost');
        $relation->count('author');
    }
}
