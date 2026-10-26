<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface RelationInterface
{
    /**
     * @param literal-string $name
     */
    public function relation(string $name): RelationConfigInterface;

    /**
     * Lets the client count a relation (`withCount`), without loading it.
     *
     * @param literal-string $name
     */
    public function count(string $name): static;
}
