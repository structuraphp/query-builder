<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

/**
 * An article whose view count is never serialized.
 */
class SecretViewsArticle extends Article
{
    protected $table = 'articles';

    protected $hidden = ['views'];
}
