<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ScopeRangeBuilder extends Builder
{
    public function viewsBetween(int $min, int $max): static
    {
        $this->getQuery()->whereBetween('views', [$min, $max]);

        return $this;
    }
}
