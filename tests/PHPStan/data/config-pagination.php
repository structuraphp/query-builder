<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

abstract class PaginationQuery implements FromModel, WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }
}

final class DefaultNotAllowedQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(PaginationMode::None);
    }
}

final class OnlyCursorQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->allow(PaginationMode::Cursor);
    }
}

final class DefaultAfterAllowQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination
            ->allow(PaginationMode::Offset)
            ->default(PaginationMode::Cursor)
            ->allow(PaginationMode::Cursor, PaginationMode::None);
    }
}

final class NotAllowedAfterAllowQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination
            ->allow(PaginationMode::Offset)
            ->default(PaginationMode::None);
    }
}

final class NamedModeQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(mode: PaginationMode::None);
    }
}

final class ExplicitOffsetQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(PaginationMode::Offset);
    }
}

final class ExplicitCursorQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default(PaginationMode::Cursor);
    }
}

final class LimitBelowQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination
            ->maxLimit(100)
            ->limit(0);
    }
}

final class LimitAboveQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination
            ->maxLimit(100)
            ->limit(101);
    }
}

final class LimitNamedQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(limit: 0);
    }
}

final class MaxBelowDefaultLimitQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination
            ->maxLimit(10);
    }
}

final class MaxEqualsDefaultLimitQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->maxLimit(50);
    }
}

final class MaxBelowEqualQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->maxLimit(49);
    }
}

final class MaxNamedQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->maxLimit(max: 0);
    }
}

final class LimitUpToDefaultMaxQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(100);
    }
}

final class LimitAboveDefaultMaxQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(101);
    }
}

final class LimitOneQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(1)->maxLimit(1);
    }
}

final class LastCallWinsQuery extends PaginationQuery
{
    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(0)->maxLimit(0);
        $pagination->limit(100)->maxLimit(100);
    }
}
