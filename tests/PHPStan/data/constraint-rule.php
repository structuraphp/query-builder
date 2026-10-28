<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;

final class AnnotatedCategoryQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Category::class;
    }

    public function relation(RelationInterface $relation): void
    {
        // Every annotation that can be given passes.
        $relation->relation('products')->constrain(static fn ($query) => 1);
        $relation->relation('products')
            ->constrain(static fn (HasMany $query) => $query);
        $relation->relation('products')
            ->constrain(
                /** @param HasMany<Product, Category> $query */
                static fn (HasMany $query) => $query,
            );
        $relation->relation('products')
            ->constrain(static fn (HasMany|ProductBuilder $query) => $query);
        $relation->relation('products')
            ->constrain(static fn (Relation $query) => $query);
        $relation->count('products')
            ->constrain(static fn (ProductBuilder $query) => $query);
        $relation->count('products')
            ->constrain(static fn (Builder $query) => $query);
        $relation->count('products')
            ->constrain(static fn (HasMany|ProductBuilder $query) => $query);
        $relation->count('products')
            ->constrain(
                /** @param Builder<Product> $query */
                static fn (Builder $query) => $query,
            );
        $relation->count('tags')
            ->constrain(
                /** @param Builder<Tag> $query */
                static fn (Builder $query) => $query,
            );
        $relation->relation('products')->constrain(static fn () => 1);

        // Wrong annotations.
        $relation->relation('products')
            ->constrain(static fn (int $query) => $query);
        $relation->relation('products')
            ->constrain(static function (BelongsTo $query): void {});
        $relation->count('products')
            ->constrain(static fn (HasMany $query) => $query);

        // No type to check against.
        $relation->relation('loose')
            ->constrain(static fn (int $query) => $query);
        $closure = static fn (int $query) => $query;
        $relation->relation('products')->constrain($closure);
        $relation->relation('products')->constrain(...);
        $method = 'constrain';
        $relation->relation('products')
            ->{$method}(static fn (int $query) => $query);
        $other = new OtherRelations();
        $other->relation('products')
            ->constrain(static fn (int $query) => $query);
        $other->missing(static fn (int $query) => $query);
    }
}
