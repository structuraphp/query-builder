<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;

/**
 * A domain service: it never sees the HTTP request.
 *
 * @phpstan-type Page (
 *     Collection<int, Model>
 *     |CursorPaginator<int, Model>
 *     |LengthAwarePaginator<int, Model>
 * )
 */
final class ArticleService
{
    /**
     * @return Page
     */
    public function list(
        QueryParameters $parameters,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        return $parameters->paginate($parameters->builder());
    }
}
