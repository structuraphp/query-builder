<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

class PrivateScopeArticle extends Article
{
    /**
     * @param Builder<self> $query
     */
    private function scopeSecret(Builder $query): void {}

    /**
     * @param Builder<self> $query
     */
    #[Scope]
    private function hush(Builder $query): void {}
}

final class ScopeNotFoundQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return PrivateScopeArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
        $scope->scope('popular');
        $scope->scope('Popular');
        $scope->scope('minViews');
        $scope->scope('author');
        $scope->scope('secret');
        $scope->scope('hush');
    }
}
