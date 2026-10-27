<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;

final class BrokenConstraintQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Category::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('comments')
            ->constrain(static fn (Comment $query) => $query);
    }
}
