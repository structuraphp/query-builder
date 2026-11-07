<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use Closure;
use StructuraPhp\QueryBuilder\Contract\Config\SortConfigInterface;

final class SortConfig implements SortConfigInterface
{
    public private(set) ?string $column = null;

    public private(set) ?string $count = null;

    /** @var array<string, Closure(): string|string> */
    public private(set) array $messages = [];

    /** @var null|(Closure(): string)|string */
    public private(set) Closure|string|null $attribute = null;

    public function __construct(
        public readonly string $name,
    ) {}

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function count(string $relation): static
    {
        $this->count = $relation;

        return $this;
    }

    public function messages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    public function attribute(Closure|string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
