<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;

/**
 * The builder the query declares, chained then paginated.
 */
final class ProductService
{
    /**
     * @return (
     *     Collection<int, Product>
     *     |CursorPaginator<int, Product>
     *     |LengthAwarePaginator<int, Product>
     * )
     */
    public function list(): Collection|CursorPaginator|LengthAwarePaginator
    {
        $parameters = QueryParameters::fromArray(new ProductQuery(), []);

        return $parameters->paginate($parameters->builder()->with([]));
    }

    /**
     * @return (
     *     Collection<int, Product>
     *     |CursorPaginator<int, Product>
     *     |LengthAwarePaginator<int, Product>
     * )
     */
    public function index(
        ProductRequest $request,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        $parameters = $request->parameters();

        return $parameters->paginate($parameters->builder()->with([]));
    }
}
