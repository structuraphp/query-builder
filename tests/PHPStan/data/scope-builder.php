<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleBuilder;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\OverriddenBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ScopeTypedArticle;

class PropertyBuilderArticle extends Model
{
    protected static string $builder = ArticleBuilder::class;
}

/**
 * The builder is an ArticleBuilder, or a subclass of it.
 */
class LooseBuilderArticle extends Model
{
    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return Builder<static>
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ArticleBuilder($query);
    }
}

class ResolvedBuilderArticle extends Model
{
    protected function resolveCustomBuilderClass(): string
    {
        return ArticleBuilder::class;
    }
}

class InheritedBuilderArticle extends ScopeTypedArticle {}

/**
 * @template TModel of Model
 *
 * @extends ArticleBuilder<TModel>
 */
class GuardedArticleBuilder extends ArticleBuilder
{
    public static function shared(): void {}

    protected function guarded(): void {}
}

#[UseEloquentBuilder(GuardedArticleBuilder::class)]
trait UsesGuardedBuilder {}

class TraitBuilderArticle extends Model
{
    use UsesGuardedBuilder;
}

final class AttributeBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('delete');
        $scope->scope('where');
    }
}

final class InheritedBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return InheritedBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsBetween');
        $scope->scope('missing');
    }
}

final class PropertyBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return PropertyBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('missing');
    }
}

final class OverriddenBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return OverriddenBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('missing');
    }
}

final class LooseBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return LooseBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class ResolvedBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return ResolvedBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class TraitBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return TraitBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('shared');
        $scope->scope('guarded');
    }
}
