<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\SortConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;

final class Sort implements SortInterface
{
    /** @var list<SortConfig> */
    public private(set) array $sorts = [];

    /** @var list<array{string, string}> */
    public private(set) array $defaults = [];

    public function sort(string $name): SortConfigInterface
    {
        return $this->sorts[] = new SortConfig($name);
    }

    public function default(string $name, string $direction = 'asc'): static
    {
        $this->defaults[] = [$name, $direction];

        return $this;
    }
}
