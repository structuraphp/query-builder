<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class SortsQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('a-b');
        $sort->sort('title');
        $sort->sort('title');
        $sort->sort('both')->column('x')->count('comments');
        $sort->sort('ghost')->count('nothing');
        $sort->sort('nested')->count('comments.author');
        $sort->sort('comments')->count('comments');
        $sort->sort('date')->column('created_at');
        $sort->default('missing');
        $sort->default('title', Direction::Desc);
        $sort->default('comments');
    }
}

final class CursorSortsQuery implements FromModel, WithSort, WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('comments')->count('comments');
        $sort->sort('title');
        $sort->sort('title')->count('comments');
        $sort->default('title');
        $sort->default('comments', Direction::Desc);
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(PaginationMode::Cursor);
    }
}

final class OffsetSortsQuery implements FromModel, WithSort, WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('comments')->count('comments');
        $sort->default('comments');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Offset, PaginationMode::Cursor);
    }
}
