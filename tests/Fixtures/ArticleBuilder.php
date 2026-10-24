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
class ArticleBuilder extends Builder
{
    public function viewsAtLeast(int $min): static
    {
        $this->getQuery()->where('views', '>=', $min);

        return $this;
    }
}
