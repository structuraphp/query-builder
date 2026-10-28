<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Closure;
use LogicException;
use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;

use function PHPStan\Testing\assertType;

class Category extends Model
{
    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function loose(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return null|HasMany<Product, $this>
     */
    public function maybe(): ?HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<Draft, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }
}

/**
 * @extends Builder<Product>
 */
final class ProductBuilder extends Builder {}

#[UseEloquentBuilder(ProductBuilder::class)]
class Product extends Model
{
    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

class Tag extends Model {}

/**
 * The builder may be a subclass of Builder.
 */
class Draft extends Model
{
    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return Builder<static>
     */
    public function newEloquentBuilder($query): Builder
    {
        return new Builder($query);
    }
}

final class User {}

final class CategoryQuery implements FromModel, WithRelation
{
    private const string DATA = __NAMESPACE__ . '\\';

    private const string RELATIONS = 'Illuminate\Database\Eloquent\Relations\\';

    public function getModel(): string
    {
        return Category::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->fields(['id'])
            ->constrain(static fn ($query) => assertType(
                self::RELATIONS . 'HasMany<' . self::DATA . 'Product, '
                    . self::DATA . 'Category>',
                $query,
            ));
        $relation->relation('tags')
            ->constrain(static fn ($query) => assertType(
                self::RELATIONS . 'BelongsToMany<' . self::DATA . 'Tag, '
                    . self::DATA . 'Category, ' . self::RELATIONS
                    . "Pivot, 'pivot'>",
                $query,
            ));
        $relation->relation('products.category')
            ->constrain(static function ($query, User $user): void {
                assertType(
                    self::RELATIONS . 'BelongsTo<' . self::DATA . 'Category, '
                        . self::DATA . 'Product>',
                    $query,
                );
                assertType(self::DATA . 'User', $user);
            });
        $relation->count('products')
            ->constrain(static fn ($query) => assertType(
                self::DATA . 'ProductBuilder',
                $query,
            ));
        $relation->count('tags')
            ->constrain(static fn ($query) => assertType(
                'Illuminate\Database\Eloquent\Builder<' . self::DATA . 'Tag>',
                $query,
            ));

        // No exact type.
        $relation->relation('loose')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation('models')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation('maybe')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation('missing')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation('products.missing')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation('missing.products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation(random_int(0, 1) === 1 ? 'products' : 'tags')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->count('drafts')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->count('products.category')
            ->constrain(static fn ($query) => assertType('mixed', $query));

        $products = $relation->relation('products');
        $products->constrain(static fn ($query) => assertType('mixed', $query));
        $products->fields(['id'])
            ->constrain(static fn ($query) => assertType('mixed', $query));

        $method = 'relation';
        $relation->{$method}('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $relation->relation()
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $this->config('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        Configs::relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
        $other = new OtherRelations();
        $other->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }

    private function config(string $name): RelationConfigInterface
    {
        throw new LogicException($name);
    }
}

final class Configs
{
    public static function relation(string $name): RelationConfigInterface
    {
        throw new LogicException($name);
    }
}

/**
 * Not the configuration of the library.
 */
final class OtherRelations
{
    public function relation(string $name): self
    {
        return $this;
    }

    public function constrain(Closure $constraint): self
    {
        return $this;
    }
}

function outsideQuery(RelationInterface $relation): void
{
    $relation->relation('products')
        ->constrain(static fn ($query) => assertType('mixed', $query));
}

function anonymousQuery(): FromModel
{
    return new class implements FromModel, WithRelation {
        public function getModel(): string
        {
            return Category::class;
        }

        public function relation(RelationInterface $relation): void
        {
            $relation->relation('products')
                ->constrain(static fn ($query) => assertType('mixed', $query));
        }
    };
}

final class ModellessQuery implements WithRelation
{
    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

abstract class AbstractCategoryQuery implements FromModel, WithRelation
{
    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

final class InheritedCategoryQuery extends AbstractCategoryQuery
{
    public function getModel(): string
    {
        return Category::class;
    }
}

/**
 * A child may return another model.
 */
class OpenCategoryQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Category::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

final class ConstantModelQuery implements FromModel, WithRelation
{
    private const string MODEL = Category::class;

    public function getModel(): string
    {
        return self::MODEL;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

final class DynamicModelQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Category::{'class'};
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

final class OwnClassQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return $this::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

final class StatementModelQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        // Not returned.
        Category::class;

        return Tag::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('products')
            ->constrain(static fn ($query) => assertType('mixed', $query));
    }
}

final class ParametersQuery implements FromModel, WithRelation
{
    public function getModel(): string
    {
        return Category::class;
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->count('tags')->constrain(
            static function (
                $query,
                User $user,
                &$seen,
                int $limit = 1,
                string ...$tags,
            ): void {},
        );
    }
}
