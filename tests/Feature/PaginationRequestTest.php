<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Override;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleService;
use Tests\StructuraPhp\QueryBuilder\Fixtures\PaginationQueryRequest;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\PlainArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

/**
 * The request validates with the compiled rules, but keeps the hooks of a
 * Laravel form request.
 */
final class PaginationRequestTest extends TestCase
{
    public function testRequestAttributesAndMessagesNameTheError(): void
    {
        $response = $this->fetchCustom(['limit' => 101]);

        $response->assertUnprocessable();
        self::assertSame(
            'The page size cannot exceed 100.',
            self::errorMessage($response, 'limit'),
        );
    }

    public function testStopOnFirstFailureReportsOneError(): void
    {
        $response = $this->fetchCustom(['limit' => 101, 'page' => 0]);

        $response
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors('page');
    }

    public function testCursorWithCountSortIs422OverHttp(): void
    {
        $this->instance(ConfigurableQuery::class, new ConfigurableQuery(
            sort: static function (SortInterface $sort): void {
                $sort->sort('comments')->count('comments');
            },
        ));

        $response = $this->fetch(ConfigurableQuery::class, [
            'pagination' => 'cursor',
            'sort' => ['comments' => 'desc'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    #[Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::get('/articles-custom', static fn (
            PaginationQueryRequest $request,
        ): Collection|CursorPaginator|LengthAwarePaginator => (
            new ArticleService()->list($request->parameters())
        ));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return TestResponse<Response>
     */
    private function fetchCustom(array $params): TestResponse
    {
        PaginationQueryRequest::$queryClass = PlainArticleQuery::class;

        return $this->getJson('/articles-custom?' . http_build_query($params));
    }
}
