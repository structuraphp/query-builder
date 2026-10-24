<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Override;

/**
 * Declares its builder by overriding newEloquentBuilder().
 */
class OverriddenBuilderArticle extends Model
{
    protected $table = 'articles';

    /**
     * @param Builder $query
     *
     * @return ArticleBuilder<$this>
     */
    #[Override]
    public function newEloquentBuilder($query): ArticleBuilder
    {
        return new ArticleBuilder($query)->setModel($this);
    }
}
