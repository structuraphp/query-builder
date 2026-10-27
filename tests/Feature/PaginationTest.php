<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\ValueObject\Config\Pagination;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\CursorArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\PlainArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\UnboundedArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class PaginationTest extends TestCase
{
    public function testPlainQueryReturnsAllArticles(): void
    {
        $this->seedArticles(3);

        $response = $this->fetch(PlainArticleQuery::class);

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function testDefaultIsOffset50(): void
    {
        $this->seedArticles(60);

        $response = $this->fetch(PlainArticleQuery::class);

        $response
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('per_page', 50);
    }

    public function testPageAndLimit(): void
    {
        $this->seedArticles(60);

        $response = $this->fetch(PlainArticleQuery::class, [
            'page' => 2,
            'limit' => 25,
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('current_page', 2);
    }

    public function testLimitAboveMaxIsRejected(): void
    {
        $response = $this->fetch(PlainArticleQuery::class, ['limit' => 101]);

        $response->assertUnprocessable()->assertJsonValidationErrors('limit');
    }

    public function testNoneIsForbiddenByDefault(): void
    {
        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'none',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pagination');
    }

    public function testNoneWhenAllowedReturnsCollection(): void
    {
        $this->seedArticles(60);

        $response = $this->fetch(UnboundedArticleQuery::class, [
            'pagination' => 'none',
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(60)
            ->assertJsonMissingPath('data');
    }

    public function testCursorPagesAreStable(): void
    {
        $this->seedArticles(60);

        $ids = $this->walkCursor(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'limit' => 25,
        ]);

        self::assertSame(range(1, 60), $ids);
    }

    public function testUnreadableCursorIs422(): void
    {
        $this->seedArticles(3);

        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'cursor' => 'xxx',
        ]);

        $response->assertUnprocessable();
        self::assertSame(
            'The selected cursor is invalid.',
            self::errorMessage($response, 'cursor'),
        );
    }

    public function testCursorMissingParameterIs422(): void
    {
        $this->seedArticles(3);
        $cursor = base64_encode(
            (string) json_encode(['foo' => 1, '_pointsToNextItems' => true]),
        );

        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'cursor' => $cursor,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function testConfigDefaultAndLimit(): void
    {
        $this->seedArticles(20);

        $response = $this->fetch(CursorArticleQuery::class);

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure(['next_cursor']);
    }

    public function testHugePageIs422(): void
    {
        $response = $this->fetch(PlainArticleQuery::class, [
            'page' => PHP_INT_MAX,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function testCursorWithNonScalarParameterDoesNot500(): void
    {
        $this->seedArticles(3);
        $ok = [200, 422];
        $cursor = base64_encode(
            (string) json_encode([
                'articles.id' => ['a' => 1],
                '_pointsToNextItems' => true,
            ]),
        );

        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'cursor' => $cursor,
        ]);

        self::assertContains($response->baseResponse->getStatusCode(), $ok);
    }

    public function testCursorBreaksTiesOnTheKey(): void
    {
        $this->seedArticles(5);
        $this->instance(ConfigurableQuery::class, $this->sortable());

        $ids = $this->walkCursor(ConfigurableQuery::class, [
            'pagination' => 'cursor',
            'limit' => 2,
            'sort' => ['status' => 'asc'],
        ]);

        self::assertSame(range(1, 5), $ids);
    }

    public function testCursorSortedOnTheKeyAddsNoTieBreaker(): void
    {
        $this->seedArticles(3);
        $this->instance(ConfigurableQuery::class, $this->sortable());
        DB::enableQueryLog();

        $response = $this->fetch(ConfigurableQuery::class, [
            'pagination' => 'cursor',
            'sort' => ['id' => 'desc'],
        ]);

        $response->assertOk();
        self::assertSame([3, 2, 1], self::column($response, 'id'));
        self::assertStringEndsWith(
            'order by ' . DB::getQueryGrammar()->wrap('articles.id')
            . ' desc limit 51',
            $this->lastQuery(),
        );
    }

    public function testCursorOnAJoinedQuery(): void
    {
        $this->seedArticles(3);
        DB::table('comments')->insert([
            ['article_id' => 1, 'body' => 'a'],
            ['article_id' => 3, 'body' => 'b'],
        ]);
        $input = ['pagination' => 'cursor', 'limit' => 1];

        $first = $this->joinedPage($input);
        $next = $first->nextCursor()?->encode();
        $second = $this->joinedPage([...$input, 'cursor' => $next]);

        self::assertSame([1], Collection::make($first->items())->modelKeys());
        self::assertSame([3], Collection::make($second->items())->modelKeys());
    }

    /**
     * PostgreSQL rejects text compared with a bigint: SQLite does not, so
     * the engine error is simulated.
     */
    public function testCursorRejectedByTheEngineIs422(): void
    {
        $this->seedArticles(3);
        DB::connection()->beforeExecuting(
            static function (string $sql, array $bindings): void {
                if (\in_array('abc', $bindings, true)) {
                    throw new QueryException(
                        'testing',
                        $sql,
                        $bindings,
                        new RuntimeException('invalid input syntax for bigint'),
                    );
                }
            },
        );

        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'cursor' => new Cursor(['articles.id' => 'abc'])->encode(),
        ]);

        $response->assertUnprocessable();
        self::assertSame(
            'The selected cursor is invalid.',
            self::errorMessage($response, 'cursor'),
        );
    }

    public function testCursorWithANonScalarValueIs422(): void
    {
        $this->seedArticles(3);

        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'cursor' => new Cursor(['articles.id' => [1, 2]])->encode(),
        ]);

        $response->assertUnprocessable();
        self::assertSame(
            'The selected cursor is invalid.',
            self::errorMessage($response, 'cursor'),
        );
    }

    public function testArrayInputIgnoresThePageOfTheRequest(): void
    {
        $this->seedArticles(3);
        request()->query->set('page', '2');
        $parameters = QueryParameters::fromArray(new PlainArticleQuery(), [
            'limit' => 2,
        ]);

        $page = $parameters->paginate($parameters->builder());

        self::assertInstanceOf(LengthAwarePaginator::class, $page);
        self::assertSame(1, $page->currentPage());
    }

    public function testUnvalidatedModeIsALogicError(): void
    {
        $definition = Definition::fromQuery(new PlainArticleQuery());
        $parameters = new QueryParameters($definition, [
            'pagination' => 'all',
        ]);

        $this->expectException(LogicException::class);

        $parameters->paginate($parameters->builder());
    }

    public function testModesAllowedByNameStayAList(): void
    {
        $pagination = new Pagination()->allow(
            first: PaginationMode::Offset,
            then: PaginationMode::None,
        );

        self::assertSame(
            [PaginationMode::Offset, PaginationMode::None],
            $pagination->allowed,
        );
    }

    private function sortable(): ConfigurableQuery
    {
        return new ConfigurableQuery(sort: static function (
            SortInterface $s,
        ): void {
            $s->sort('id');
            $s->sort('status');
        });
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return CursorPaginator<int, Model>
     */
    private function joinedPage(array $input): CursorPaginator
    {
        $query = new PlainArticleQuery();
        $parameters = QueryParameters::fromArray($query, $input);
        $builder = $parameters->builder();
        $builder->getQuery()
            ->join('comments', 'comments.article_id', '=', 'articles.id')
            ->select('articles.*');
        $page = $parameters->paginate($builder);
        self::assertInstanceOf(CursorPaginator::class, $page);

        return $page;
    }

    private function lastQuery(): string
    {
        $log = DB::getQueryLog();
        $query = end($log)['query'] ?? null;
        self::assertIsString($query);

        return $query;
    }

    private function seedArticles(int $count): void
    {
        DB::table('articles')->insert(
            array_map(
                static fn (int $i): array => ['title' => "Article {$i}"],
                range(1, $count),
            ),
        );
    }
}
