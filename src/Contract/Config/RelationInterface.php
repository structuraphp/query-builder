<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface RelationInterface
{
    public function relation(string $name): RelationConfigInterface;

    /**
     * Lets the client count a relation (`withCount`), without loading it.
     */
    public function count(string $name): static;
}
