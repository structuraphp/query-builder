<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;

final class CategoryQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Category::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => $query->where('active', true));
        $relation->relation('featured')
            ->constrain(static fn (HasMany $query) => $query);
        $relation->relation('archived')
            ->constrain(
                /** @param HasMany<Product, Category> $query */
                static fn (HasMany $query) => $query,
            );
        $relation->relation('discounted')
            ->constrain(static fn (HasMany|ProductBuilder $query) => $query);
        $relation->relation('refurbished')
            ->constrain(static fn (Relation $query) => $query);

        $relation->count('products')
            ->constrain(static fn ($query) => $query->where('active', true));
        $relation->count('featured')
            ->constrain(static fn (ProductBuilder $query) => $query);
        $relation->count('archived')
            ->constrain(static fn (Builder $query) => $query);
        $relation->count('discounted')
            ->constrain(static fn (HasMany|ProductBuilder $query) => $query);
        $relation->count('refurbished')
            ->constrain(
                /** @param Builder<Product> $query */
                static fn (Builder $query) => $query,
            );
        $relation->count('comments')
            ->constrain(
                /** @param Builder<Comment> $query */
                static fn (Builder $query) => $query,
            );
    }
}
