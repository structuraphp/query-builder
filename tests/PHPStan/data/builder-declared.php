<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Builder;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleBuilder;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\OverriddenBuilderArticle;

/**
 * @implements FromModel<ArticleBuilder<BuiltArticle>>
 */
final class DeclaredBuilderQuery implements FromModel
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }
}

/**
 * The builder of the model is a Builder.
 *
 * @implements FromModel<Builder<BuiltArticle>>
 */
final class ParentBuilderQuery implements FromModel
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }
}

final class UndeclaredBuilderQuery implements FromModel
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }
}

/**
 * @implements FromModel<ArticleBuilder<Article>>
 */
final class OtherBuilderQuery implements FromModel
{
    public function getModel(): string
    {
        return Article::class;
    }
}

/**
 * @implements FromModel<ArticleBuilder<Article>>
 */
final class OtherModelQuery implements FromModel
{
    public function getModel(): string
    {
        return BuiltArticle::class;
    }
}

/**
 * Its builder is unknown: newEloquentBuilder() of a non final class.
 *
 * @implements FromModel<Builder<OverriddenBuilderArticle>>
 */
final class UnknownBuilderQuery implements FromModel
{
    public function getModel(): string
    {
        return OverriddenBuilderArticle::class;
    }
}
