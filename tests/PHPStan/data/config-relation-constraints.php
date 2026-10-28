<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Builder;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class RelationConstraintsQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('comments')->constrain(fn ($query) => $query);
        $relation->count('ghost')->constrain(fn (Builder $query) => $query);
        $relation->count('comments')->constrain(fn ($query) => $query);
        $relation->count('comments')->constrain(fn ($query) => $query);
    }
}
