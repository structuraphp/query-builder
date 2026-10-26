<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
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

    public function testValidatorOnlyGetsTheRulesOfGivenParameters(): void
    {
        $keys = [];
        $factory = $this->app?->make(Factory::class);
        self::assertInstanceOf(Factory::class, $factory);
        $factory->resolver(static function (
            Translator $translator,
            array $data,
            array $rules,
            array $messages,
            array $attributes,
        ) use (&$keys): Validator {
            $keys = array_keys($rules);

            return new Validator(
                $translator,
                $data,
                $rules,
                $messages,
                $attributes,
            );
        });

        QueryParameters::fromArray(
            new FilterArticleQuery(),
            ['title' => ['value' => 'Foo']],
            $factory,
        );

        self::assertSame(
            [
                'title',
                'title.value',
                'title.value.*',
                'title.mode',
                'title.not',
            ],
            $keys,
        );
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
            'title' => ['value' => 'Foo', 'mode' => 'gt'],
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

    public function testFromArrayUsesTheGivenValidator(): void
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'validation', [
            'integer' => 'Not a number.',
        ]);

        $validator = new Factory(new Translator($loader, 'en'));
        $errors = [];

        try {
            QueryParameters::fromArray(new FilterArticleQuery(), [
                'views' => ['value' => 'abc'],
            ], $validator);
        } catch (ValidationException $validationException) {
            $errors = $validationException->errors();
        }

        self::assertSame(['views.value' => ['Not a number.']], $errors);
    }
}
