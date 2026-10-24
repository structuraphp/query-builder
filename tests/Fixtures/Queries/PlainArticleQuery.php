<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class PlainArticleQuery implements FromModel
{
    public function getModel(): string
    {
        return Article::class;
    }
}
