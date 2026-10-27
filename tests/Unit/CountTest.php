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
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
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

        self::assertSame(1, substr_count($sql, $this->countAlias()));
    }

    /**
     * Laravel nests one closure per repeated relation: tens of thousands of
     * repeats overflowed the stack and crashed the worker.
     */
    public function testRepeatedCountIsCountedOnce(): void
    {
        $parameters = QueryParameters::fromArray($this->articleQuery(), [
            'count' => implode(',', array_fill(0, 50_000, 'comments')),
        ]);

        $sql = $parameters->builder()->getQuery()->toSql();

        self::assertSame(1, substr_count($sql, $this->countAlias()));
    }

    public function testSortBySeveralCounts(): void
    {
        DB::table('authors')->insert([
            'id' => 1,
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret',
        ]);
        DB::table('articles')->where('id', 1)->update(['author_id' => 1]);
        $query = new ConfigurableQuery(
            sort: static function (SortInterface $sort): void {
                $sort->sort('author')->count('author');
                $sort->sort('comments')->count('comments');
            },
        );

        $rows = $this->rows(
            ['sort' => ['author' => 'desc', 'comments' => 'desc']],
            $query,
        );

        self::assertSame(['Foo', 'Bar', 'Baz'], array_column($rows, 'title'));
        self::assertSame([1, 0, 0], array_column($rows, 'author_count'));
        self::assertSame([0, 2, 1], array_column($rows, 'comments_count'));
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
            static fn (): ConfigurableQuery => new ConfigurableQuery(
                relation: static function (RelationInterface $r): void {
                    $r->count('comments')->count('comments');
                },
            ),
        ];

        yield 'reserved name' => [
            static fn (): ConfigurableQuery => new ConfigurableQuery(
                filter: static function (FilterInterface $f): void {
                    $f->filter('count')->type(Type::Integer);
                },
            ),
        ];

        yield 'sort on an unknown relation' => [
            static fn (): ConfigurableQuery => new ConfigurableQuery(
                sort: static function (SortInterface $s): void {
                    $s->sort('tags')->count('tags');
                },
            ),
        ];

        yield 'sort with column and count' => [
            static fn (): ConfigurableQuery => new ConfigurableQuery(
                sort: static function (SortInterface $s): void {
                    $s->sort('comments')->column('views')->count('comments');
                },
            ),
        ];

        yield 'cursor by default with a default count sort' => [
            static fn (): ConfigurableQuery => new ConfigurableQuery(
                pagination: static function (PaginationInterface $p): void {
                    $p->default(PaginationMode::Cursor);
                },
                sort: self::defaultCountSort(...),
            ),
        ];
    }

    /**
     * @param literal-string $name
     *
     * @return Closure(): ConfigurableQuery
     */
    private static function counting(string $name): Closure
    {
        return static fn (): ConfigurableQuery => new ConfigurableQuery(
            relation: static function (RelationInterface $r) use ($name): void {
                $r->count($name);
            },
        );
    }

    /**
     * Quoted by the engine: `"comments_count"`, or backticks on MySQL.
     */
    private function countAlias(): string
    {
        return 'as ' . DB::getQueryGrammar()->wrap('comments_count');
    }

    private static function defaultCountSort(SortInterface $sort): void
    {
        $sort->sort('comments')->count('comments');
        $sort->default('comments', Direction::Desc);
    }

    /**
     * @param null|(Closure(SortInterface): void) $sort
     */
    private function articleQuery(?Closure $sort = null): ConfigurableQuery
    {
        return new ConfigurableQuery(
            relation: static function (RelationInterface $relation): void {
                $relation->relation('author');
                $relation->count('comments');
            },
            sort: $sort ?? static function (SortInterface $sort): void {
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
