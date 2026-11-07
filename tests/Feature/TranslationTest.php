<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Closure;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\Service\ValidationCompiler;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class TranslationTest extends TestCase
{
    public function testSortMessageAndAttribute(): void
    {
        $query = $this->sortQuery();

        $errors = $this->errors($query, ['sort' => ['date' => 'sideways']]);

        self::assertSame(['sort.date' => ['Bad date order.']], $errors);
    }

    public function testRequestAnswersWithTheTranslation(): void
    {
        $this->app?->bind(
            ConfigurableQuery::class,
            fn (): ConfigurableQuery => $this->sortQuery(),
        );
        TestQueryRequest::$queryClass = ConfigurableQuery::class;

        $response = $this->getJson('/articles?sort[date]=sideways');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort.date' => 'Bad date order.']);
    }

    public function testAFilterIsNamedOnEveryKey(): void
    {
        $query = new ConfigurableQuery(filter: static function (
            FilterInterface $filter,
        ): void {
            $filter->filter('views')->type(Type::Integer)->attribute('Views');
        });

        $attributes = new ValidationCompiler(Definition::fromQuery($query))
            ->attributes();

        self::assertSame(
            ['views.value', 'views.value.*', 'views.mode', 'views.not'],
            array_keys($attributes),
        );
    }

    public function testEverySortIsNamed(): void
    {
        $query = new ConfigurableQuery(sort: static function (
            SortInterface $sort,
        ): void {
            $sort->sort('date')->column('created_at')->attribute('Date');
            $sort->sort('title')->attribute('Title');
            $sort->sort('views');
        });

        $attributes = new ValidationCompiler(Definition::fromQuery($query))
            ->attributes();

        self::assertSame(['sort.date', 'sort.title'], array_keys($attributes));
    }

    public function testSortRequiredMessage(): void
    {
        $query = $this->sortQuery();

        $errors = $this->errors($query, ['sort' => ['date' => '']]);

        self::assertSame(['sort.date' => ['Missing date order.']], $errors);
    }

    public function testSortWithoutTranslationKeepsTheDefaults(): void
    {
        $query = new ConfigurableQuery(sort: static function (
            SortInterface $sort,
        ): void {
            $sort->sort('date')->column('created_at');
        });
        Lang::addLines(['validation.in' => 'Bad :attribute.'], 'en');

        $errors = $this->errors($query, ['sort' => ['date' => 'sideways']]);

        self::assertSame(['sort.date' => ['Bad sort.date.']], $errors);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('pagination')]
    public function testPaginationMessagesAndAttributes(
        array $input,
        string $key,
        string $message,
    ): void {
        $query = new ConfigurableQuery(pagination: static function (
            PaginationInterface $pagination,
        ): void {
            $pagination
                ->attribute(
                    pagination: 'mode',
                    limit: 'per page',
                    page: 'page number',
                    cursor: 'bookmark',
                )
                ->messages(
                    pagination: ['in' => 'Unknown :attribute.'],
                    limit: [
                        'max' => 'The :attribute is too high.',
                        'min' => 'The :attribute is too low.',
                        'integer' => 'The :attribute is not a number.',
                    ],
                    page: ['min' => 'The :attribute starts at 1.'],
                    cursor: ['string' => 'The :attribute is not text.'],
                );
        });

        $errors = $this->errors($query, $input);

        self::assertSame([$key => [$message]], $errors);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function pagination(): iterable
    {
        yield 'limit too high' => [
            ['limit' => 500],
            'limit',
            'The per page is too high.',
        ];

        yield 'limit too low' => [
            ['limit' => 0],
            'limit',
            'The per page is too low.',
        ];

        yield 'limit not a number' => [
            ['limit' => 'many'],
            'limit',
            'The per page is not a number.',
        ];

        yield 'page too low' => [
            ['page' => 0],
            'page',
            'The page number starts at 1.',
        ];

        yield 'unknown mode' => [
            ['pagination' => 'infinite'],
            'pagination',
            'Unknown mode.',
        ];

        yield 'cursor not text' => [
            ['cursor' => ['a']],
            'cursor',
            'The bookmark is not text.',
        ];
    }

    public function testUnreadableCursorUsesTheTranslation(): void
    {
        $query = new ConfigurableQuery(pagination: static function (
            PaginationInterface $pagination,
        ): void {
            $pagination->default(PaginationMode::Cursor)
                ->attribute(cursor: 'bookmark')
                ->messages(cursor: ['in' => 'Unreadable :attribute.']);
        });

        $errors = $this->cursorErrors($query);

        self::assertSame(['cursor' => ['Unreadable bookmark.']], $errors);
    }

    public function testUnreadableCursorKeepsTheDefaultMessage(): void
    {
        $query = new ConfigurableQuery(pagination: static function (
            PaginationInterface $pagination,
        ): void {
            $pagination->default(PaginationMode::Cursor)
                ->attribute(cursor: 'bookmark');
        });
        Lang::addLines(['validation.in' => 'Bad :attribute.'], 'en');

        $errors = $this->cursorErrors($query);

        self::assertSame(['cursor' => ['Bad bookmark.']], $errors);
    }

    public function testUnreadableCursorWithoutTranslation(): void
    {
        $query = new ConfigurableQuery(pagination: static function (
            PaginationInterface $pagination,
        ): void {
            $pagination->default(PaginationMode::Cursor);
        });
        Lang::addLines(['validation.in' => 'Bad :attribute.'], 'en');

        $errors = $this->cursorErrors($query);

        self::assertSame(['cursor' => ['Bad cursor.']], $errors);
    }

    public function testNothingRunsWhileTheRequestIsValid(): void
    {
        $calls = [];
        $query = $this->countingQuery($calls);

        QueryParameters::fromArray($query, [
            'views' => ['value' => '3'],
            'sort' => ['date' => 'asc'],
            'limit' => 10,
        ]);

        self::assertSame([], $calls);
    }

    public function testOnlyWhatFailsRuns(): void
    {
        $calls = [];
        $query = $this->countingQuery($calls);

        $errors = $this->errors($query, [
            'views' => ['value' => 'many'],
            'sort' => ['date' => 'asc'],
        ]);

        self::assertSame(
            ['views.value' => ['Views is not a number.']],
            $errors,
        );
        ksort($calls);
        self::assertSame(['filter message' => 1, 'filter name' => 1], $calls);
    }

    public function testFailureResolvesEachClosureOnce(): void
    {
        $calls = [];
        $query = $this->countingQuery($calls);

        $errors = $this->errors($query, [
            'views' => ['value' => 'many'],
            'sort' => ['date' => 'sideways'],
            'limit' => 500,
        ]);

        self::assertSame(
            [
                'views.value' => ['Views is not a number.'],
                'sort.date' => ['Bad sort name.'],
                'limit' => ['The page size is too high.'],
            ],
            $errors,
        );
        ksort($calls);
        self::assertSame([
            'filter message' => 1,
            'filter name' => 1,
            'limit message' => 1,
            'limit name' => 1,
            'sort message' => 1,
            'sort name' => 1,
        ], $calls);
    }

    public function testScopeAttributeAsAClosure(): void
    {
        $query = new ConfigurableQuery(scope: static function (
            ScopeInterface $scope,
        ): void {
            $scope->scope('since')
                ->messages(date: ['date_format' => 'Not a date: :attribute.'])
                ->attribute(date: static fn (): string => 'start');
        });

        $errors = $this->errors($query, ['since' => ['date' => 'tomorrow']]);

        self::assertSame(['since.date' => ['Not a date: start.']], $errors);
    }

    public function testNestedScopeAttributeAsAClosure(): void
    {
        $query = new ConfigurableQuery(scope: static function (
            ScopeInterface $scope,
        ): void {
            $scope->scope('among')
                ->nested(ids: ['*' => ['integer']])
                ->messages(ids: ['*.integer' => 'Bad :attribute.'])
                ->attribute(ids: ['*' => static fn (): string => 'identifier']);
        });

        $errors = $this->errors($query, ['among' => ['ids' => ['x']]]);

        self::assertSame(['among.ids.0' => ['Bad identifier.']], $errors);
    }

    public function testClosureCastFailureUsesTheAttributeClosure(): void
    {
        $query = new ConfigurableQuery(scope: static function (
            ScopeInterface $scope,
        ): void {
            $scope->scope('since')
                ->cast(date: static function (mixed $value): never {
                    throw new InvalidArgumentException('no');
                })
                ->attribute(date: static fn (): string => 'start');
        });
        Lang::addLines(['validation.in' => 'Bad :attribute.'], 'en');

        $errors = $this->errors($query, ['since' => ['date' => '2025-01-01']]);

        self::assertSame(['since.date' => ['Bad start.']], $errors);
    }

    private function sortQuery(): ConfigurableQuery
    {
        return new ConfigurableQuery(sort: static function (
            SortInterface $sort,
        ): void {
            $sort->sort('date')
                ->column('created_at')
                ->attribute('date order')
                ->messages([
                    'in' => 'Bad :attribute.',
                    'required' => 'Missing :attribute.',
                ]);
        });
    }

    /**
     * @param array<string, int> $calls
     */
    private function countingQuery(array &$calls): ConfigurableQuery
    {
        $count = static function (
            string $name,
            string $text,
        ) use (&$calls): Closure {
            return static function () use (&$calls, $name, $text): string {
                $calls[$name] = ($calls[$name] ?? 0) + 1;

                return $text;
            };
        };

        return new ConfigurableQuery(
            filter: static function (
                FilterInterface $filter,
            ) use ($count): void {
                $filter->filter('views')
                    ->type(Type::Integer)
                    ->messages([
                        'integer' => $count(
                            'filter message',
                            ':attribute is not a number.',
                        ),
                    ])
                    ->attribute($count('filter name', 'Views'));
            },
            pagination: static function (
                PaginationInterface $pagination,
            ) use ($count): void {
                $pagination
                    ->attribute(limit: $count('limit name', 'page size'))
                    ->messages(limit: [
                        'max' => $count(
                            'limit message',
                            'The :attribute is too high.',
                        ),
                    ]);
            },
            sort: static function (SortInterface $sort) use ($count): void {
                $sort->sort('date')
                    ->column('created_at')
                    ->attribute($count('sort name', 'sort name'))
                    ->messages([
                        'in' => $count('sort message', 'Bad :attribute.'),
                    ]);
            },
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<mixed>
     */
    private function errors(ConfigurableQuery $query, array $input): array
    {
        try {
            QueryParameters::fromArray($query, $input);
        } catch (ValidationException $validationException) {
            return $validationException->errors();
        }

        self::fail('The input should be invalid.');
    }

    /**
     * @return array<mixed>
     */
    private function cursorErrors(ConfigurableQuery $query): array
    {
        $parameters = QueryParameters::fromArray($query, ['cursor' => '!!']);

        try {
            $parameters->paginate($parameters->builder());
        } catch (ValidationException $validationException) {
            return $validationException->errors();
        }

        self::fail('The cursor should be refused.');
    }
}
