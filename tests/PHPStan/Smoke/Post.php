<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /**
     * @param Builder<self> $query
     */
    public function scopePublished(Builder $query): void {}
}
