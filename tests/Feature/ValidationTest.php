<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Symfony\Component\HttpFoundation\Response;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\FilterArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ScopeArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\SortArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['title' => 'Foo', 'views' => 5],
            ['title' => 'Bar', 'views' => 50],
            ['title' => 'Baz', 'views' => 500],
        ]);
    }

    public function testClosureCastRunsDespiteAnotherInvalidParameter(): void
    {
        $query = new ConfigurableQuery(
            scope: function (ScopeInterface $scope): void {
                $scope->scope('minViews')->cast(min: $this->reject(...));
            },
        );

        $errors = $this->errors(
            ['minViews' => ['min' => '5'], 'limit' => 'abc'],
            $query,
        );

        self::assertSame(['limit', 'minViews.min'], array_keys($errors));
    }

    public function testFailingClosureCastNamesTheArgument(): void
    {
        $query = new ConfigurableQuery(
            scope: function (ScopeInterface $scope): void {
                $scope->scope('minViews')
                    ->cast(min: $this->reject(...))
                    ->attribute(min: 'vues minimum');
            },
        );

        $errors = $this->errors(['minViews' => ['min' => '5']], $query);

        self::assertSame(
            ['minViews.min' => ['The selected vues minimum is invalid.']],
            $errors,
        );
    }

    public function testClosureCastOfALaterArgumentRuns(): void
    {
        $query = new ConfigurableQuery(
            scope: function (ScopeInterface $scope): void {
                $scope->scope('publishedBetween')->cast(to: $this->reject(...));
            },
        );

        $errors = $this->errors(
            ['publishedBetween' => [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]],
            $query,
        );

        self::assertSame(['publishedBetween.to'], array_keys($errors));
    }

    public function testValidatedKeepsOnlyDeclaredScopeArguments(): void
    {
        $response = $this->validated(ScopeArticleQuery::class, [
            'minViews' => ['min' => '100', 'foo' => '1'],
        ]);

        $response
            ->assertOk()
            ->assertExactJson(['minViews' => ['min' => '100']]);
    }

    public function testValidatedKeepsOnlyDeclaredFilterKeys(): void
    {
        $response = $this->validated(FilterArticleQuery::class, [
            'title' => ['value' => 'Foo', 'foo' => '1'],
        ]);

        $response->assertOk()->assertExactJson(['title' => ['value' => 'Foo']]);
    }

    public function testRelationListMustBeAString(): void
    {
        $errors = $this->errors(
            ['with' => ['author']],
            $this->countQuery(),
        );

        self::assertSame(['with'], array_keys($errors));
    }

    public function testEachAndNestedItemRulesBothApply(): void
    {
        $query = new ConfigurableQuery(
            scope: function (ScopeInterface $scope): void {
                $scope->scope('among')
                    ->each(ids: ['integer', 'min:1'])
                    ->nested(ids: ['*' => ['max:100']]);
            },
        );

        $tooSmall = $this->errors(['among' => ['ids' => ['0']]], $query);
        $tooBig = $this->errors(['among' => ['ids' => ['500']]], $query);

        self::assertSame(['among.ids.0'], array_keys($tooSmall));
        self::assertSame(['among.ids.0'], array_keys($tooBig));
    }

    public function testFilterAttributeNamesTheFilterInMessages(): void
    {
        $query = new ConfigurableQuery(
            filter: function (FilterInterface $filter): void {
                $filter->filter('title')
                    ->type(Type::String)
                    ->attribute('titre');
            },
        );

        $errors = $this->errors(
            ['title' => ['value' => 'Foo', 'mode' => 'gt']],
            $query,
        );

        self::assertSame(
            ['title.mode' => ['The selected titre is invalid.']],
            $errors,
        );
    }

    public function testNullableFilterTakesAListInInMode(): void
    {
        $query = new ConfigurableQuery(
            filter: function (FilterInterface $filter): void {
                $filter->filter('views')->type(Type::Integer)->nullable();
            },
        );

        $builder = QueryParameters::fromArray($query, [
            'views' => ['mode' => 'in', 'value' => ['5', '500']],
        ])->builder();
        $builder->getQuery()->orderBy('id');
        $titles = $builder->pluck('title')->all();

        self::assertSame(['Foo', 'Baz'], $titles);
    }

    public function testModeMissingFromTheTypeStillValidatesTheValue(): void
    {
        $errors = $this->errors(
            ['featured' => ['mode' => 'in', 'value' => 'maybe']],
            $this->filterQuery(),
        );

        self::assertSame(
            ['featured.value', 'featured.mode'],
            array_keys($errors),
        );
    }

    public function testFilterRulesApplyAfterTheTypeRule(): void
    {
        $errors = $this->errors(
            ['published_at' => ['value' => '2023-01-01']],
            $this->filterQuery(),
        );

        self::assertSame(['published_at.value'], array_keys($errors));
    }

    public function testFilterWithoutInOrNullModeNeedsAValue(): void
    {
        $errors = $this->errors(
            ['featured' => ['not' => '1']],
            $this->filterQuery(),
        );

        self::assertSame(['featured.value'], array_keys($errors));
    }

    public function testNullableFilterNeedsAValueOutsideNullMode(): void
    {
        $query = new ConfigurableQuery(
            filter: function (FilterInterface $filter): void {
                $filter->filter('published_at')->type(Type::Date)->nullable();
            },
        );

        $errors = $this->errors(
            ['published_at' => ['mode' => 'gte']],
            $query,
        );

        self::assertSame(['published_at.value'], array_keys($errors));
    }

    public function testUndeclaredSectionsAreProhibited(): void
    {
        $errors = $this->errors(
            ['sort' => ['title' => 'asc'], 'with' => 'author', 'count' => 'x'],
            new ConfigurableQuery(),
        );

        self::assertSame(
            [
                'sort' => ['The sort field is prohibited.'],
                'with' => ['The with field is prohibited.'],
                'count' => ['The count field is prohibited.'],
            ],
            $errors,
        );
    }

    public function testSeveralUndeclaredRelationsGiveOneError(): void
    {
        $errors = $this->errors(
            ['with' => 'foo,bar'],
            $this->countQuery(),
        );

        self::assertSame(
            ['with' => ['The selected with is invalid.']],
            $errors,
        );
    }

    public function testCursorWithCountSortMessage(): void
    {
        $errors = $this->errors(
            ['pagination' => 'cursor', 'sort' => ['comments' => 'desc']],
            $this->countQuery(),
        );

        self::assertSame(
            ['sort' => ['The selected sort is invalid.']],
            $errors,
        );
    }

    public function testInvalidSortDirectionIsTheOnlyCursorError(): void
    {
        $errors = $this->errors(
            ['pagination' => 'cursor', 'sort' => ['comments' => 'up']],
            $this->countQuery(),
        );

        self::assertSame(['sort.comments'], array_keys($errors));
    }

    public function testRefusedCursorIsTheOnlyError(): void
    {
        $query = $this->countQuery(
            function (PaginationInterface $pagination): void {
                $pagination->allow(PaginationMode::Offset);
            },
        );

        $errors = $this->errors(
            ['pagination' => 'cursor', 'sort' => ['comments' => 'desc']],
            $query,
        );

        self::assertSame(['pagination'], array_keys($errors));
    }

    public function testPrecognitionIgnoresAnUndeclaredCursorSort(): void
    {
        TestQueryRequest::$queryClass = SortArticleQuery::class;

        $response = $this->withHeaders([
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'limit',
        ])
            ->getJson(
                '/articles-precog?' . http_build_query([
                    'pagination' => 'cursor',
                    'sort' => ['views' => 'asc'],
                    'limit' => 10,
                ]),
            );

        $response->assertNoContent();
    }

    /**
     * What a controller gets from `$request->validated()`.
     *
     * @param class-string<FromModel> $query
     * @param array<string, mixed> $params
     *
     * @return TestResponse<Response>
     */
    private function validated(string $query, array $params): TestResponse
    {
        Route::get(
            '/articles-validated',
            fn (TestQueryRequest $request): mixed => $request->validated(),
        );
        TestQueryRequest::$queryClass = $query;

        return $this->getJson(
            '/articles-validated?' . http_build_query($params),
        );
    }

    private function reject(mixed $value): never
    {
        throw new RuntimeException();
    }

    private function filterQuery(): ConfigurableQuery
    {
        return new ConfigurableQuery(
            filter: function (FilterInterface $filter): void {
                $filter->filter('featured')->type(Type::Boolean);
                $filter->filter('published_at')
                    ->type(Type::Date)
                    ->rules(['after_or_equal:2024-01-01']);
            },
        );
    }

    /**
     * @param null|(Closure(PaginationInterface): void) $pagination
     */
    private function countQuery(?Closure $pagination = null): ConfigurableQuery
    {
        return new ConfigurableQuery(
            pagination: $pagination,
            relation: function (RelationInterface $relation): void {
                $relation->relation('author');
                $relation->count('comments');
            },
            sort: function (SortInterface $sort): void {
                $sort->sort('comments')->count('comments');
            },
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<mixed>
     */
    private function errors(array $input, ConfigurableQuery $query): array
    {
        try {
            QueryParameters::fromArray($query, $input);
        } catch (ValidationException $validationException) {
            return $validationException->errors();
        }

        return [];
    }
}
