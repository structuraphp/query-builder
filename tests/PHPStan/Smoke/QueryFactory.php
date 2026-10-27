<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;

final class QueryFactory
{
    /**
     * Its reflection only exists in the worker that analyses this file.
     */
    public static function anonymous(): FromModel
    {
        return new class implements FromModel, WithScope {
            public function getModel(): string
            {
                return Post::class;
            }

            public function scope(ScopeInterface $scope): void
            {
                $scope->scope('ghost');
            }
        };
    }
}
