<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;

final class LiteralNamesQuery
{
    private const string TITLE = 'title';
    private const string SUFFIX = 'tle';

    public function filter(FilterInterface $filter, string $name): void
    {
        $filter->filter($name);
        $filter->filter('title');
        $filter->filter(self::TITLE);
        $filter->filter('ti' . self::SUFFIX);
    }

    public function sort(SortInterface $sort, string $name): void
    {
        $sort->sort(strtolower($name));
    }

    public function fields(
        RelationInterface $relation,
        string $column,
    ): void {
        $relation->relation('author')->fields([$column]);
    }
}
