<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;

final class RelationConfig implements RelationConfigInterface
{
    /** @var list<string> */
    public private(set) array $fields = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }
}
