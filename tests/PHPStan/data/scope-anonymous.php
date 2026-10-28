<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

function anonymousQuery(): FromModel
{
    // Its reflection only exists in the worker that analyses this file.
    return new class implements FromModel, WithScope {
        public function getModel(): string
        {
            return Article::class;
        }

        public function scope(ScopeInterface $scope): void
        {
            $scope->scope('ghost');
        }
    };
}
