<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class CountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['title' => 'Foo'],
            ['title' => 'Bar'],
            ['title' => 'Baz'],
        ]);
        DB::table('comments')->insert([
            ['article_id' => 2, 'body' => 'a'],
            ['article_id' => 2, 'body' => 'b'],
            ['article_id' => 3, 'body' => 'c'],
        ]);
    }

    public function testCountAddsTheAttribute(): void
    {
        $rows = $this->rows(['count' => 'comments']);

        self::assertSame(
            ['Foo' => 0, 'Bar' => 2, 'Baz' => 1],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testCountedRelationIsNotLoadable(): void
    {
        $errors = $this->errors(['with' => 'comments']);

        self::assertArrayHasKey('with', $errors);
    }

    public function testUndeclaredCountIs422(): void
    {
        $errors = $this->errors(['count' => 'author']);

        self::assertArrayHasKey('count', $errors);
    }

    public function testCountIsProhibitedWithoutDeclaredCounts(): void
    {
        $query = new ConfigurableQuery();

        $errors = $this->errors(['count' => 'comments'], $query);

        self::assertArrayHasKey('count', $errors);
    }

    public function testSortByCount(): void
    {
        $rows = $this->rows(['sort' => ['comments' => 'desc']]);

        self::assertSame(['Bar', 'Baz', 'Foo'], array_column($rows, 'title'));
        self::assertSame([2, 1, 0], array_column($rows, 'comments_count'));
    }

    public function testCountedAndSortedRelationIsCountedOnce(): void
    {
        $parameters = QueryParameters::fromArray($this->articleQuery(), [
            'count' => 'comments',
            'sort' => ['comments' => 'asc'],
        ]);

        $sql = $parameters->builder()->getQuery()->toSql();

        self::assertSame(1, substr_count($sql, 'as "comments_count"'));
    }

    public function testDefaultSortByCount(): void
    {
        $query = $this->articleQuery(self::defaultCountSort(...));

        $rows = $this->rows([], $query);

        self::assertSame(['Bar', 'Baz', 'Foo'], array_column($rows, 'title'));
    }

    public function testCursorWithCountSortIs422(): void
    {
        $errors = $this->errors([
            'pagination' => 'cursor',
            'sort' => ['comments' => 'desc'],
        ]);

        self::assertArrayHasKey('sort', $errors);
    }

    public function testCursorWithDefaultCountSortIs422(): void
    {
        $query = $this->articleQuery(self::defaultCountSort(...));

        $errors = $this->errors(['pagination' => 'cursor'], $query);

        self::assertArrayHasKey('pagination', $errors);
    }

    /**
     * @param Closure(): ConfigurableQuery $query
     */
    #[DataProvider('invalidConfigs')]
    public function testConfigErrors(Closure $query): void
    {
        $this->expectException(ConfigurationException::class);

        QueryParameters::fromArray($query(), []);
    }

    /**
     * @return iterable<string, array{Closure(): ConfigurableQuery}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown relation' => [self::counting('tags')];

        yield 'nested relation' => [self::counting('author.profile')];

        yield 'declared twice' => [
            fn (): ConfigurableQuery => new ConfigurableQuery(
                relation: function (RelationInterface $r): void {
                    $r->count('comments')->count('comments');
                },
            ),
        ];

        yield 'reserved name' => [
            fn (): ConfigurableQuery => new ConfigurableQuery(
                filter: function (FilterInterface $f): void {
                    $f->filter('count')->type(Type::Integer);
                },
            ),
        ];

        yield 'sort on an unknown relation' => [
            fn (): ConfigurableQuery => new ConfigurableQuery(
                sort: function (SortInterface $s): void {
                    $s->sort('tags')->count('tags');
                },
            ),
        ];

        yield 'sort with column and count' => [
            fn (): ConfigurableQuery => new ConfigurableQuery(
                sort: function (SortInterface $s): void {
                    $s->sort('comments')->column('views')->count('comments');
                },
            ),
        ];

        yield 'cursor by default with a default count sort' => [
            fn (): ConfigurableQuery => new ConfigurableQuery(
                pagination: function (PaginationInterface $p): void {
                    $p->default('cursor');
                },
                sort: self::defaultCountSort(...),
            ),
        ];
    }

    /**
     * @return Closure(): ConfigurableQuery
     */
    private static function counting(string $name): Closure
    {
        return fn (): ConfigurableQuery => new ConfigurableQuery(
            relation: function (RelationInterface $r) use ($name): void {
                $r->count($name);
            },
        );
    }

    private static function defaultCountSort(SortInterface $sort): void
    {
        $sort->sort('comments')->count('comments');
        $sort->default('comments', 'desc');
    }

    /**
     * @param null|(Closure(SortInterface): void) $sort
     */
    private function articleQuery(?Closure $sort = null): ConfigurableQuery
    {
        return new ConfigurableQuery(
            relation: function (RelationInterface $relation): void {
                $relation->relation('author');
                $relation->count('comments');
            },
            sort: $sort ?? function (SortInterface $sort): void {
                $sort->sort('title');
                $sort->sort('comments')->count('comments');
            },
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $input, ?ConfigurableQuery $query = null): array
    {
        $query ??= $this->articleQuery();
        $builder = QueryParameters::fromArray($query, $input)->builder();

        /** @var list<array<string, mixed>> */
        return $builder->get()->toArray();
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<mixed>
     */
    private function errors(
        array $input,
        ?ConfigurableQuery $query = null,
    ): array {
        try {
            QueryParameters::fromArray($query ?? $this->articleQuery(), $input);
        } catch (ValidationException $validationException) {
            return $validationException->errors();
        }

        return [];
    }
}
