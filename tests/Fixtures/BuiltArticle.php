<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Declares its builder with the attribute, and a model scope that the
 * builder method of the same name overrides: its other type tells them
 * apart.
 */
#[UseEloquentBuilder(ArticleBuilder::class)]
class BuiltArticle extends Model
{
    protected $table = 'articles';

    /**
     * @param Builder<self> $query
     */
    public function scopeViewsAtLeast(Builder $query, string $min): void
    {
        $query->getQuery()->where('views', '<', $min);
    }
}
