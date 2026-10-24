<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use StructuraPhp\QueryBuilder\Service\QueryRequest;

/**
 * The parameter type picks the form request Laravel resolves.
 *
 * @phpstan-type Page (
 *     Collection<int, Model>
 *     |CursorPaginator<int, Model>
 *     |LengthAwarePaginator<int, Model>
 * )
 */
final class ArticleController
{
    /**
     * @return Page
     */
    public function index(
        TestQueryRequest $request,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        return $this->paginate($request);
    }

    /**
     * @return Page
     */
    public function override(
        OverridingQueryRequest $request,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        return $this->paginate($request);
    }

    /**
     * @return Page
     */
    public function data(
        ValidationDataQueryRequest $request,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        return $this->paginate($request);
    }

    /**
     * @return Page
     */
    private function paginate(
        QueryRequest $request,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        return new ArticleService()->list($request->parameters());
    }
}
