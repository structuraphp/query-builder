<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class CursorArticleQuery implements FromModel, WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default('cursor')->limit(10);
    }
}
