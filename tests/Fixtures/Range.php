<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

final readonly class Range
{
    public function __construct(
        public int $min,
        public int $max,
    ) {}
}
