<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class ScopeAliasQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('statut')->method('ofStatus');
        $scope->scope('popular')
            ->method('nope');
        $scope->scope('missing')->method('nope')->method('popular');
        $config = $scope->scope('since');
        $config->method('later');
        $scope = $scope->scope('lost');
        $scope->method('popular');
    }
}
