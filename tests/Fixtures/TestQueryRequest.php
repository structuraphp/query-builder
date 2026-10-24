<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Service\QueryRequest;

class TestQueryRequest extends QueryRequest
{
    /** @var class-string<FromModel> */
    public static string $queryClass;

    protected function getQuery(): string
    {
        return static::$queryClass;
    }
}
