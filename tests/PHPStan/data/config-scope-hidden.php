<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\SecretViewsArticle;

#[Hidden(['title'])]
class HiddenTitleArticle extends Article {}

final class HiddenSortsQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return SecretViewsArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('views');
        $sort->sort('popularity')
            ->column('views');
        $sort->sort('seen')->count('comments');
        $sort->sort('title');
        $sort->sort('byDate')->column('created_at');
    }
}

final class HiddenAttributeSortsQuery implements
    FromModel,
    WithSort,
    WithPaginate
{
    public function getModel(): string
    {
        return HiddenTitleArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('title');
        $sort->sort('views');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(PaginationMode::Cursor);
        $pagination->allow(PaginationMode::Cursor);
    }
}

final class HiddenOffsetSortsQuery implements
    FromModel,
    WithSort,
    WithPaginate
{
    public function getModel(): string
    {
        return SecretViewsArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('views');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Offset);
    }
}

final class HiddenAllowedSortsQuery implements
    FromModel,
    WithSort,
    WithPaginate
{
    public function getModel(): string
    {
        return SecretViewsArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('views');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Offset);
        $pagination->allow(PaginationMode::Offset, PaginationMode::Cursor);
    }
}

final class HiddenUnreadableSortsQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return SecretViewsArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort(self::name());
        $sort->sort('other')->column(self::name());
        $sort->sort('title')->column('views')->column(self::name());
    }

    private static function name(): string
    {
        return 'views';
    }
}

final class HiddenCountSortsQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return SecretViewsArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('views')->count('comments');
    }
}

final class HiddenUnreadableModeQuery implements
    FromModel,
    WithSort,
    WithPaginate
{
    public function getModel(): string
    {
        return SecretViewsArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('views');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Offset, self::mode());
    }

    private static function mode(): PaginationMode
    {
        return PaginationMode::Offset;
    }
}

final class UnsetHiddenSortsQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return UnsetHiddenArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('secret');
    }
}

final class StringHiddenSortsQuery implements FromModel, WithSort
{
    public function getModel(): string
    {
        return StringHiddenArticle::class;
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('secret');
    }
}
