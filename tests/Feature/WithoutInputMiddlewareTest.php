<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\CursorArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\FilterArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ScopeArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\SortArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

/**
 * API apps often drop the empty-string middlewares: blank values must still
 * be rejected.
 */
final class WithoutInputMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConvertEmptyStringsToNull::skipWhen(fn (): true => true);
        TrimStrings::skipWhen(fn (): true => true);
    }

    protected function tearDown(): void
    {
        ConvertEmptyStringsToNull::flushState();
        TrimStrings::flushState();

        parent::tearDown();
    }

    /**
     * @param class-string<FromModel> $query
     * @param array<string, mixed> $params
     */
    #[DataProvider('blankValues')]
    public function testBlankValueIs422(
        string $query,
        array $params,
        string $key,
    ): void {
        $response = $this->fetch($query, $params);

        $response->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    /**
     * @return iterable<string, array{
     *     class-string<FromModel>,
     *     array<string, mixed>,
     *     string,
     * }>
     */
    public static function blankValues(): iterable
    {
        yield 'filter mode' => [
            FilterArticleQuery::class,
            ['title' => ['value' => 'Foo', 'mode' => '']],
            'title.mode',
        ];

        yield 'filter mode blank' => [
            FilterArticleQuery::class,
            ['title' => ['value' => 'Foo', 'mode' => ' ']],
            'title.mode',
        ];

        yield 'filter not' => [
            FilterArticleQuery::class,
            ['title' => ['value' => 'Foo', 'not' => '']],
            'title.not',
        ];

        yield 'optional scope argument' => [
            ScopeArticleQuery::class,
            ['minViews' => ['min' => '']],
            'minViews.min',
        ];

        yield 'no-arg scope' => [
            ScopeArticleQuery::class,
            ['popular' => ''],
            'popular',
        ];

        yield 'sort direction' => [
            SortArticleQuery::class,
            ['sort' => ['title' => '']],
            'sort.title',
        ];

        yield 'pagination' => [
            CursorArticleQuery::class,
            ['pagination' => ''],
            'pagination',
        ];

        yield 'page' => [CursorArticleQuery::class, ['page' => ''], 'page'];

        yield 'limit' => [CursorArticleQuery::class, ['limit' => ''], 'limit'];

        yield 'cursor' => [
            CursorArticleQuery::class,
            ['cursor' => ''],
            'cursor',
        ];
    }
}
