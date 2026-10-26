<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes typed with what Article does not cover, and a builder method with
 * several arguments.
 */
#[UseEloquentBuilder(ScopeRangeBuilder::class)]
class ScopeTypedArticle extends Model
{
    protected $table = 'articles';

    /**
     * @param Builder<self> $query
     */
    public function scopeFeatured(Builder $query, bool $featured): void
    {
        $query->where('featured', $featured);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeRatedAbove(Builder $query, float $rating): void
    {
        $query->where('rating', '>', $rating);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeOfPriority(
        Builder $query,
        ScopePriority $priority,
    ): void {
        $query->where('views', '>=', $priority->value);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeOfVersion(Builder $query, ScopeVersion $version): void
    {
        $query->where('status', $version->value);
    }

    /**
     * @param Builder<self> $query
     * @param list<int> $ids
     * @param list<string> $titles
     */
    public function scopeMatching(
        Builder $query,
        array $ids,
        array $titles,
    ): void {
        $query->getQuery()->whereIn('id', $ids)->whereIn('title', $titles);
    }
}
