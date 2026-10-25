<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Support\Facades\DB;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\PlainArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\SortArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class SortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['id' => 1, 'title' => 'B', 'created_at' => '2024-01-01'],
            ['id' => 2, 'title' => 'A', 'created_at' => '2024-01-02'],
            ['id' => 3, 'title' => 'B', 'created_at' => '2024-01-03'],
            ['id' => 4, 'title' => 'A', 'created_at' => '2024-01-04'],
        ]);
    }

    public function testTitleThenDate(): void
    {
        $response = $this->fetch(SortArticleQuery::class, [
            'sort' => ['title' => 'asc', 'date' => 'desc'],
        ]);

        $response->assertOk();
        self::assertSame([4, 2, 3, 1], self::column($response, 'id'));
    }

    public function testDateThenTitle(): void
    {
        $response = $this->fetch(SortArticleQuery::class, [
            'sort' => ['date' => 'desc', 'title' => 'asc'],
        ]);

        $response->assertOk();
        self::assertSame([4, 3, 2, 1], self::column($response, 'id'));
    }

    public function testDefaultSortWhenAbsent(): void
    {
        $response = $this->fetch(SortArticleQuery::class);

        $response->assertOk();
        self::assertSame([4, 3, 2, 1], self::column($response, 'id'));
    }

    public function testClientSortReplacesDefault(): void
    {
        $response = $this->fetch(SortArticleQuery::class, [
            'sort' => ['title' => 'asc'],
        ]);

        $response->assertOk();
        self::assertSame([2, 4, 1, 3], self::column($response, 'id'));
    }

    public function testUndeclaredSortIs422(): void
    {
        $response = $this->fetch(SortArticleQuery::class, [
            'sort' => ['views' => 'asc'],
        ]);

        $response->assertUnprocessable();
        self::assertSame(
            'The selected sort is invalid.',
            self::errorMessage($response, 'sort'),
        );
    }

    public function testBadDirectionIs422(): void
    {
        $response = $this->fetch(SortArticleQuery::class, [
            'sort' => ['title' => 'up'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort.title');
    }

    public function testSortWithoutWithSortIs422(): void
    {
        $response = $this->fetch(PlainArticleQuery::class, [
            'sort' => ['title' => 'asc'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function testCursorWithSort(): void
    {
        $ids = $this->walkCursor(SortArticleQuery::class, [
            'pagination' => 'cursor',
            'limit' => 2,
            'sort' => ['title' => 'asc'],
        ]);

        self::assertSame([2, 4, 1, 3], $ids);
    }
}
