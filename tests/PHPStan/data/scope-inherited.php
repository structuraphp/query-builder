<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;

final class ArticleChildQuery extends InheritedScopeQuery
{
    public function getModel(): string
    {
        return Article::class;
    }
}

final class BuiltChildQuery extends InheritedScopeQuery
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }
}

final class BuiltSiblingQuery extends InheritedScopeQuery
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }
}

final class OverridingChildQuery extends InheritedScopeQuery
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
    }
}
