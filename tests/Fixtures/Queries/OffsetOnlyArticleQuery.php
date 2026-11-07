<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class OffsetOnlyArticleQuery implements FromModel, WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Offset)->limit(20);
    }
}
