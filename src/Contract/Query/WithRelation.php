<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;

interface WithRelation
{
    public function relation(RelationInterface $relation): void;
}
