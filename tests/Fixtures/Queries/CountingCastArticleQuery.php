<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Service\TypeCaster;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class CountingCastArticleQuery implements FromModel, WithScope
{
    public static int $calls = 0;

    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('near')->cast(
            lat: function (mixed $value): float {
                self::$calls++;

                return (float) TypeCaster::scalar($value);
            },
            lng: Type::Float,
        );
    }
}
