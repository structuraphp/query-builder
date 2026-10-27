<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Contracts\Database\Eloquent\SupportsPartialRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * What Laravel needs to load or count a declared relation: the
 * fields() and the constrain() of its configuration.
 */
final readonly class RelationConstraints
{
    public function __construct(
        private Definition $definition,
    ) {}

    /**
     * @return Closure(Relation<*, *, *>): void|string
     */
    public function eagerLoad(string $path): Closure|string
    {
        $config = $this->definition->relations[$path];
        $fields = $config->fields;
        $constraint = $config->constraint;

        if ($constraint === null) {
            return $fields === []
                ? $path
                : $path . ':' . implode(',', $fields);
        }

        return static function (Relation $relation) use (
            $constraint,
            $fields,
        ): void {
            $constraint($relation);

            if ($fields === []) {
                return;
            }

            // Laravel qualifies the columns the same way for `path:columns`.
            if (
                $relation instanceof BelongsToMany
                || $relation instanceof HasOneOrManyThrough
                || (
                    $relation instanceof SupportsPartialRelations
                    && $relation->isOneOfMany()
                )
            ) {
                $fields = array_map(
                    $relation->getRelated()->qualifyColumn(...),
                    $fields,
                );
            }

            $relation->getBaseQuery()->select($fields);
        };
    }

    /**
     * @return null|Closure(Builder<Model>): void
     */
    public function count(string $name): ?Closure
    {
        $constraint = ($this->definition->counts[$name] ?? null)?->constraint;
        if ($constraint === null) {
            return null;
        }

        return static function (Builder $query) use ($constraint): void {
            $constraint($query);
        };
    }
}
