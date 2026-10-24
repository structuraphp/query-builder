<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\FilterArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ScopeArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class QueryParametersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['title' => 'Foo', 'status' => 'draft', 'views' => 5],
            ['title' => 'Bar', 'status' => 'published', 'views' => 50],
        ]);
    }

    public function testFromArrayAppliesTheQuery(): void
    {
        $parameters = QueryParameters::fromArray(new FilterArticleQuery(), [
            'title' => ['value' => 'Foo'],
        ]);

        $titles = $parameters->builder()->pluck('title')->all();

        self::assertSame(['Foo'], $titles);
    }

    public function testFromArrayPaginates(): void
    {
        $parameters = QueryParameters::fromArray(new FilterArticleQuery(), [
            'limit' => 1,
        ]);

        $page = $parameters->paginate($parameters->builder());

        self::assertInstanceOf(LengthAwarePaginator::class, $page);
        self::assertCount(1, $page->items());
    }

    public function testFromArrayDetectsRequestedScopes(): void
    {
        $parameters = QueryParameters::fromArray(new ScopeArticleQuery(), [
            'minViews' => ['foo' => 1],
        ]);

        $titles = $parameters->builder()->pluck('title')->all();

        self::assertSame(['Bar'], $titles);
    }

    public function testFromArrayRejectsInvalidInput(): void
    {
        $this->expectException(ValidationException::class);

        QueryParameters::fromArray(new FilterArticleQuery(), [
            'title' => ['value' => 'Foo', 'mode' => '>'],
        ]);
    }

    public function testFromArrayUsesConfiguredMessages(): void
    {
        $errors = [];

        try {
            QueryParameters::fromArray(new FilterArticleQuery(), [
                'statut' => ['value' => 'foo'],
            ]);
        } catch (ValidationException $validationException) {
            $errors = $validationException->errors();
        }

        self::assertSame(['statut.value' => ['Statut invalide.']], $errors);
    }
}
