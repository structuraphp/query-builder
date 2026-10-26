<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;

final class Pagination implements PaginationInterface
{
    public private(set) PaginationMode $default = PaginationMode::Offset;

    public private(set) int $limit = 50;

    public private(set) int $maxLimit = 100;

    /** @var list<PaginationMode> */
    public private(set) array $allowed = [
        PaginationMode::Offset,
        PaginationMode::Cursor,
    ];

    public function default(PaginationMode $mode): static
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

    public function allow(PaginationMode ...$modes): static
    {
        $this->allowed = array_values($modes);

        return $this;
    }
}
