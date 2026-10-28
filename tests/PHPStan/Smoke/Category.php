<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function featured(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function archived(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function discounted(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function refurbished(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
