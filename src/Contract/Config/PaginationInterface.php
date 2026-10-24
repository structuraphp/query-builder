<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

interface PaginationInterface
{
    public function default(string $mode): static;

    public function limit(int $limit): static;

    public function maxLimit(int $max): static;

    public function allow(string ...$modes): static;
}
