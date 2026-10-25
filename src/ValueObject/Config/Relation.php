<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;

final class Relation implements RelationInterface
{
    /** @var list<RelationConfig> */
    public private(set) array $relations = [];

    /** @var list<string> */
    public private(set) array $counts = [];

    public function relation(string $name): RelationConfigInterface
    {
        return $this->relations[] = new RelationConfig($name);
    }

    public function count(string $name): static
    {
        $this->counts[] = $name;

        return $this;
    }
}
