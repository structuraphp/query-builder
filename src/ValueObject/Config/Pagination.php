<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;

final class Pagination implements PaginationInterface
{
    public const array MODES = ['offset', 'cursor', 'none'];

    public private(set) string $default = 'offset';

    public private(set) int $limit = 50;

    public private(set) int $maxLimit = 100;

    /** @var list<string> */
    public private(set) array $allowed = ['offset', 'cursor'];

    public function default(string $mode): static
    {
        $this->default = $mode;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function maxLimit(int $max): static
    {
        $this->maxLimit = $max;

        return $this;
    }

    public function allow(string ...$modes): static
    {
        $this->allowed = array_values($modes);

        return $this;
    }
}
