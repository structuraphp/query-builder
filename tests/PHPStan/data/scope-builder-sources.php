<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleBuilder;

/**
 * @template TModel of Model
 *
 * @extends ArticleBuilder<TModel>
 */
class ExtraArticleBuilder extends ArticleBuilder
{
    public function extra(): static
    {
        return $this;
    }
}

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
final class FinalArticleBuilder extends Builder
{
    public function viewsAtLeast(int $min): static
    {
        return $this;
    }
}

/**
 * Declares an ArticleBuilder, returns a subclass of it.
 */
class NonFinalBuilderArticle extends Model
{
    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return ArticleBuilder<static>
     */
    public function newEloquentBuilder($query): ArticleBuilder
    {
        return new ExtraArticleBuilder($query);
    }
}

class FinalBuilderArticle extends Model
{
    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return FinalArticleBuilder<static>
     */
    public function newEloquentBuilder($query): FinalArticleBuilder
    {
        return new FinalArticleBuilder($query);
    }
}

/**
 * Laravel ignores an attribute that names no builder.
 */
#[UseEloquentBuilder(Article::class)]
class NonBuilderAttributeArticle extends Model
{
    protected static string $builder = ArticleBuilder::class;
}

#[UseEloquentBuilder('Tests\Missing\Builder')]
class MissingAttributeArticle extends Model
{
    protected static string $builder = ArticleBuilder::class;
}

class ModelQueryArticle extends Model
{
    /**
     * @return Builder<static>
     */
    public function newModelQuery()
    {
        return parent::newModelQuery();
    }
}

class NewQueryArticle extends Model
{
    /**
     * @return Builder<static>
     */
    public function newQuery()
    {
        return parent::newQuery();
    }
}

class InheritedPropertyArticle extends PropertyBuilderArticle {}

trait ScopesFromTrait
{
    /**
     * @param Builder<Model> $query
     */
    public function scopeFromTrait(Builder $query): void {}
}

class TraitScopeArticle extends Model
{
    use ScopesFromTrait;
}

final class NonFinalBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return NonFinalBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('extra');
        $scope->scope('missing');
    }
}

final class FinalBuilderQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return FinalBuilderArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('missing');
    }
}

final class NonBuilderAttributeQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return NonBuilderAttributeArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('missing');
    }
}

final class MissingAttributeQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return MissingAttributeArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('missing');
    }
}

final class ModelQueryQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return ModelQueryArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class NewQueryQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return NewQueryArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class InheritedPropertyQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return InheritedPropertyArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('viewsAtLeast');
        $scope->scope('missing');
    }
}

final class TraitScopeQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return TraitScopeArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('fromTrait');
        $scope->scope('missing');
    }
}

/**
 * Not a query: its getModel() is not read.
 */
class ModelBase
{
    public function getModel(): string
    {
        return Article::class;
    }
}

final class ModelBaseChildQuery extends ModelBase implements
    FromModel,
    WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}
