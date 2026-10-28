<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use StructuraPhp\QueryBuilder\Enum\PaginationMode;

interface PaginationInterface
{
    public function default(PaginationMode $mode): static;

    public function limit(int $limit): static;

    public function maxLimit(int $max): static;

    public function allow(PaginationMode ...$modes): static;
}
