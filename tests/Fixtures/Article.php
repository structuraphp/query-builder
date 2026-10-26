<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopePopular(Builder $query): void
    {
        $query->where('views', '>=', 100);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopePublishedBetween(
        Builder $query,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        $query
            ->getQuery()
            ->whereBetween('published_at', [
                $from->toDateString(),
                $to->toDateString(),
            ]);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeOfStatus(Builder $query, Status $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * @param Builder<self> $query
     * @param mixed $lat
     * @param mixed $lng
     */
    public function scopeNear(Builder $query, $lat, $lng): void
    {
        $query->getQuery()->whereBetween('views', [$lat, $lng]);
    }

    /**
     * @param Builder<self> $query
     * @param list<string> $statuses
     */
    public function scopeOfStatuses(Builder $query, array $statuses): void
    {
        $query->getQuery()->whereIn('status', $statuses);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeSince(Builder $query, Carbon $date): void
    {
        $query->where('published_at', '>=', $date->toDateString());
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeUnion(Builder $query, int|string $value): void
    {
        $query->where('views', $value);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeOwnedBy(
        Builder $query,
        string $status,
        Author $author,
    ): void {
        $query->where('status', $status)->where('author_id', $author->getKey());
    }

    /**
     * @param Builder<self> $query
     * @param list<int> $ids
     */
    public function scopeAmong(Builder $query, array $ids): void
    {
        $query->getQuery()->whereIn('id', $ids);
    }

    /**
     * @param Builder<self> $query
     * @param list<int> $ids
     */
    public function scopeOfStatusAmong(
        Builder $query,
        Status $status,
        array $ids,
    ): void {
        $query->where('status', $status->value);
        $query->getQuery()->whereIn('id', $ids);
    }

    /**
     * @param Builder<self> $query
     * @param list<array{min: string, max: string}> $ranges
     */
    public function scopeInRanges(Builder $query, array $ranges): void
    {
        $query->where(static function (Builder $query) use ($ranges): void {
            foreach ($ranges as $range) {
                $query->getQuery()->orWhereBetween(
                    'views',
                    [$range['min'], $range['max']],
                );
            }
        });
    }

    /**
     * @param Builder<self> $query
     * @param array{min: string, max: string} $range
     */
    public function scopeViewsWithin(Builder $query, array $range): void
    {
        $query->getQuery()->whereBetween(
            'views',
            [$range['min'], $range['max']],
        );
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeViewsInRange(Builder $query, Range $range): void
    {
        $query->getQuery()->whereBetween('views', [$range->min, $range->max]);
    }

    /**
     * @param Builder<self> $query
     * @param Closure(Builder<self>): void $callback
     */
    public function scopeTapped(Builder $query, Closure $callback): void
    {
        $callback($query);
    }

    /**
     * @param Builder<self> $query
     */
    #[Scope]
    protected function minViews(Builder $query, int $min = 10): void
    {
        $query->where('views', '>=', $min);
    }
}
