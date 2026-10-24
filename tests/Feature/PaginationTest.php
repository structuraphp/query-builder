<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Support\Facades\DB;
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

    public function testGarbageCursorDoesNot500(): void
    {
        $this->seedArticles(3);
        $ok = [200, 422];

        $response = $this->fetch(PlainArticleQuery::class, [
            'pagination' => 'cursor',
            'cursor' => 'xxx',
        ]);

        self::assertContains($response->baseResponse->getStatusCode(), $ok);
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

    private function seedArticles(int $count): void
    {
        DB::table('articles')->insert(
            array_map(
                fn (int $i): array => ['title' => "Article {$i}"],
                range(1, $count),
            ),
        );
    }
}
