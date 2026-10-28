<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
