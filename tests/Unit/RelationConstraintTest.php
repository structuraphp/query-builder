<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use ArrayObject;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class RelationConstraintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('authors')->insert([
            ['id' => 1, 'name' => 'Ada', 'email' => 'a@x.io', 'password' => ''],
            ['id' => 2, 'name' => 'Bob', 'email' => 'b@x.io', 'password' => ''],
        ]);
        DB::table('articles')->insert([
            ['title' => 'Foo', 'author_id' => 1],
            ['title' => 'Bar', 'author_id' => 2],
            ['title' => 'Baz', 'author_id' => 1],
        ]);
        DB::table('comments')->insert([
            ['article_id' => 2, 'body' => 'a'],
            ['article_id' => 2, 'body' => 'b'],
            ['article_id' => 3, 'body' => 'c'],
        ]);
    }

    public function testConstrainedRelationLoadsMatchingRowsOnly(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')->constrain(
                self::loaded('a'),
            );
        });

        $rows = $this->rows($query, ['with' => 'comments']);

        self::assertSame(
            ['Foo' => [], 'Bar' => ['a'], 'Baz' => []],
            $this->bodies($rows),
        );
    }

    public function testFieldsApplyAfterTheConstraint(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')
                ->fields(['id', 'article_id'])
                ->constrain(self::loaded('a'));
        });

        $rows = $this->rows($query, ['with' => 'comments']);

        self::assertSame(
            [
                'Foo' => [],
                'Bar' => [['id' => 1, 'article_id' => 2]],
                'Baz' => [],
            ],
            $this->comments($rows),
        );
    }

    public function testBelongsToManyFieldsAreQualified(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('tags')
                ->fields(['id', 'name'])
                ->constrain(static fn (BelongsToMany $q): BelongsToMany => $q);
        });
        $parameters = QueryParameters::fromArray($query, ['with' => 'tags']);

        $load = $parameters->builder()->getEagerLoads()['tags'];
        self::assertInstanceOf(Closure::class, $load);
        $relation = new Article()->tags();
        $load($relation);

        self::assertSame(
            ['tags.id', 'tags.name'],
            $relation->getQuery()->getQuery()->columns,
        );
    }

    public function testThroughRelationFieldsAreQualified(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('authorComments')
                ->fields(['id', 'body'])
                ->constrain(
                    static fn (HasManyThrough $q) => $q->getBaseQuery()->where(
                        'comments.body',
                        'a',
                    ),
                );
        });

        $rows = $this->rows($query, ['with' => 'authorComments']);

        self::assertSame(
            ['Foo' => [], 'Bar' => ['a'], 'Baz' => []],
            $this->bodies($rows, 'author_comments'),
        );
    }

    public function testOneOfManyFieldsAreQualified(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('latestComment')
                ->fields(['id', 'body'])
                ->constrain(static fn (HasOne $q): HasOne => $q);
        });
        $parameters = QueryParameters::fromArray(
            $query,
            ['with' => 'latestComment'],
        );

        $load = $parameters->builder()->getEagerLoads()['latestComment'];
        self::assertInstanceOf(Closure::class, $load);
        $relation = new Article()->latestComment();
        $load($relation);

        self::assertSame(
            ['comments.id', 'comments.body'],
            $relation->getQuery()->getQuery()->columns,
        );
    }

    public function testPlainRelationFieldsStayUnqualified(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')
                ->fields(['id', 'body'])
                ->constrain(static fn (HasMany $q): HasMany => $q);
        });
        $parameters = QueryParameters::fromArray(
            $query,
            ['with' => 'comments'],
        );

        $load = $parameters->builder()->getEagerLoads()['comments'];
        self::assertInstanceOf(Closure::class, $load);
        $relation = new Article()->comments();
        $load($relation);

        self::assertSame(
            ['id', 'body'],
            $relation->getQuery()->getQuery()->columns,
        );
    }

    public function testNestedPathsKeepTheirOwnConstraint(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('author')->constrain(
                static fn (BelongsTo $q) => $q->getBaseQuery()->where(
                    'name',
                    'Ada',
                ),
            );
            $r->relation('author.articles')->constrain(
                static fn (HasMany $q) => $q->getBaseQuery()->where(
                    'title',
                    'Foo',
                ),
            );
        });

        $rows = $this->rows($query, ['with' => 'author.articles']);

        $authors = array_column($rows, 'author', 'title');
        self::assertSame(
            ['Foo' => ['Foo'], 'Bar' => null, 'Baz' => ['Foo']],
            array_map(
                static fn (mixed $author): ?array => \is_array($author)
                    && \is_array($author['articles'])
                    ? array_column($author['articles'], 'title')
                    : null,
                $authors,
            ),
        );
    }

    public function testConstraintWithoutParameters(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')->constrain(
                static fn (): string => 'ignored',
            );
        });

        $rows = $this->rows($query, ['with' => 'comments']);

        self::assertSame(
            ['Foo' => [], 'Bar' => ['a', 'b'], 'Baz' => ['c']],
            $this->bodies($rows),
        );
    }

    public function testLastConstraintWins(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')
                ->constrain(self::loaded('a'))
                ->constrain(self::loaded('b'));
            $r->count('comments')
                ->constrain(self::counted('a'))
                ->constrain(self::counted('b'));
        });

        $rows = $this->rows(
            $query,
            ['with' => 'comments', 'count' => 'comments'],
        );

        self::assertSame(
            ['Foo' => [], 'Bar' => ['b'], 'Baz' => []],
            $this->bodies($rows),
        );
        self::assertSame(
            ['Foo' => 0, 'Bar' => 1, 'Baz' => 0],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testClosureReceivesTheRelationThenTheBuilder(): void
    {
        $seen = [];
        $query = $this->configure(
            static function (RelationInterface $r) use (&$seen): void {
                $r->relation('comments')->constrain(
                    static function (Relation $q) use (&$seen): void {
                        $seen['with'] = $q::class;
                    },
                );
                $r->count('comments')->constrain(
                    static function (Builder $q) use (&$seen): void {
                        $seen['count'] = $q::class;
                    },
                );
            },
        );

        $this->rows($query, ['with' => 'comments', 'count' => 'comments']);

        self::assertSame(
            ['count' => Builder::class, 'with' => HasMany::class],
            $seen,
        );
    }

    public function testConstrainedCount(): void
    {
        $rows = $this->rows($this->countQuery(), ['count' => 'comments']);

        self::assertSame(
            ['Foo' => 0, 'Bar' => 1, 'Baz' => 0],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testSortByCountFollowsTheCountConstraint(): void
    {
        $rows = $this->rows(
            $this->countQuery(),
            ['sort' => ['comments' => 'desc']],
        );

        self::assertSame('Bar', $rows[0]['title']);
        self::assertSame(
            ['Bar' => 1, 'Foo' => 0, 'Baz' => 0],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testSortOnAnUndeclaredCountIsUnconstrained(): void
    {
        $query = $this->configure(
            null,
            static function (SortInterface $s): void {
                $s->sort('comments')->count('comments');
            },
        );

        $rows = $this->rows($query, ['sort' => ['comments' => 'desc']]);

        self::assertSame([2, 1, 0], array_column($rows, 'comments_count'));
    }

    public function testOffsetPaginationCountsEveryRow(): void
    {
        $parameters = QueryParameters::fromArray(
            $this->countQuery(),
            ['count' => 'comments', 'limit' => 2],
        );

        $page = $parameters->paginate($parameters->builder());

        self::assertInstanceOf(LengthAwarePaginator::class, $page);
        self::assertSame(3, $page->total());
    }

    public function testContextReachesTheRelationConstraint(): void
    {
        $query = $this->bodyQuery();

        $rows = $this->rows(
            $query,
            ['with' => 'comments'],
            ['with' => ['comments' => ['body' => 'b']]],
        );

        self::assertSame(
            ['Foo' => [], 'Bar' => ['b'], 'Baz' => []],
            $this->bodies($rows),
        );
    }

    public function testContextReachesTheCountConstraint(): void
    {
        $rows = $this->rows(
            $this->bodyQuery(),
            ['count' => 'comments'],
            ['count' => ['comments' => ['body' => 'c']]],
        );

        self::assertSame(
            ['Foo' => 0, 'Bar' => 0, 'Baz' => 1],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testSortedCountUsesTheCountContext(): void
    {
        $rows = $this->rows(
            $this->bodyQuery(),
            ['sort' => ['comments' => 'desc']],
            ['count' => ['comments' => ['body' => 'c']]],
        );

        self::assertSame('Baz', $rows[0]['title']);
        self::assertSame(
            ['Baz' => 1, 'Foo' => 0, 'Bar' => 0],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testLoadedAndCountedRelationsGetTheirOwnContext(): void
    {
        $rows = $this->rows(
            $this->bodyQuery(),
            ['with' => 'comments', 'count' => 'comments'],
            [
                'with' => ['comments' => ['body' => 'a']],
                'count' => ['comments' => ['body' => 'c']],
            ],
        );

        self::assertSame(
            ['Foo' => [], 'Bar' => ['a'], 'Baz' => []],
            $this->bodies($rows),
        );
        self::assertSame(
            ['Foo' => 0, 'Bar' => 0, 'Baz' => 1],
            array_column($rows, 'comments_count', 'title'),
        );
    }

    public function testSeveralRelationsAndArgumentsGetTheirContext(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')->constrain(
                static fn (
                    HasMany $q,
                    string $skipped = 'zzz',
                    string $body = 'x',
                    int $min = 0,
                ) => $q->getBaseQuery()
                    ->where('body', $body)
                    ->where('id', '>=', $min),
            );
            $r->relation('authorComments')->constrain(
                static fn (
                    HasManyThrough $q,
                    string $body,
                ) => $q->getBaseQuery()->where('comments.body', $body),
            );
        });

        $rows = $this->rows(
            $query,
            ['with' => 'comments,authorComments'],
            ['with' => [
                'comments' => ['body' => 'b', 'min' => 3],
                'authorComments' => ['body' => 'c'],
            ]],
        );

        self::assertSame(
            ['Foo' => [], 'Bar' => [], 'Baz' => []],
            $this->bodies($rows),
        );
        self::assertSame(
            ['Foo' => ['c'], 'Bar' => [], 'Baz' => ['c']],
            $this->bodies($rows, 'author_comments'),
        );

        $rows = $this->rows(
            $query,
            ['with' => 'comments,authorComments'],
            ['with' => [
                'comments' => ['body' => 'b', 'min' => 2],
                'authorComments' => ['body' => 'c'],
            ]],
        );

        self::assertSame(
            ['Foo' => [], 'Bar' => ['b'], 'Baz' => []],
            $this->bodies($rows),
        );
    }

    public function testScopeContextIsCheckedNextToConstraintContext(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope ghost is not declared.');

        QueryParameters::fromArray($this->bodyQuery(), [])->builder(
            context: ['with' => [], 'count' => [], 'ghost' => []],
        );
    }

    public function testLazyContextIsOnlyCalledWhenNeeded(): void
    {
        /** @var ArrayObject<int, int> $calls */
        $calls = new ArrayObject();
        $body = static function () use ($calls): string {
            $calls[] = 1;

            return 'a';
        };
        $context = ['with' => ['comments' => ['body' => $body]]];

        $this->rows($this->bodyQuery(), [], $context);
        self::assertCount(0, $calls);

        $this->rows($this->bodyQuery(), ['with' => 'comments'], $context);
        self::assertCount(1, $calls);
    }

    public function testClosureParameterReceivesTheClosureAsIs(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')->constrain(
                static fn (HasMany $q, Closure $filter) => $filter($q),
            );
        });
        $calls = 0;
        $filter = static function (HasMany $q) use (&$calls): void {
            $calls++;
            $q->getBaseQuery()->where('body', 'a');
        };

        $rows = $this->rows(
            $query,
            ['with' => 'comments'],
            ['with' => ['comments' => ['filter' => $filter]]],
        );

        self::assertSame(1, $calls);
        self::assertSame(
            ['Foo' => [], 'Bar' => ['a'], 'Baz' => []],
            $this->bodies($rows),
        );
    }

    public function testOptionalParameterFallsBackToItsDefault(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')->constrain(
                static fn (
                    HasMany $q,
                    string $body = 'a',
                ) => $q->getBaseQuery()->where('body', $body),
            );
        });

        $rows = $this->rows($query, ['with' => 'comments']);

        self::assertSame(
            ['Foo' => [], 'Bar' => ['a'], 'Baz' => []],
            $this->bodies($rows),
        );
    }

    public function testMissingContextThrowsOnlyWhenRequested(): void
    {
        $this->rows($this->bodyQuery(), []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Missing context argument body of relation comments.',
        );

        $this->rows($this->bodyQuery(), ['with' => 'comments']);
    }

    public function testMissingCountContextNamesTheCount(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Missing context argument body of count comments.',
        );

        $this->rows($this->bodyQuery(), ['count' => 'comments']);
    }

    public function testMissingParentContextNamesTheParent(): void
    {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('author')->constrain(
                static fn (BelongsTo $q, string $name) => $q->getBaseQuery()
                    ->where('name', $name),
            );
            $r->relation('author.articles');
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Missing context argument name of relation author.',
        );

        $this->rows($query, ['with' => 'author.articles']);
    }

    /**
     * @param array<string, array<string, mixed>> $context
     */
    #[DataProvider('unknownContextKeys')]
    public function testUnknownContextKeysThrow(
        array $context,
        string $message,
    ): void {
        $query = $this->configure(static function (RelationInterface $r): void {
            $r->relation('comments')->constrain(
                static fn (
                    HasMany $q,
                    string $body,
                ) => $q->getBaseQuery()->where('body', $body),
            );
            $r->relation('tags');
            $r->relation('authorComments')->constrain(
                static fn (
                    HasManyThrough $q,
                    string ...$rest,
                ) => $q->getBaseQuery()->where('comments.body', $rest),
            );
            $r->count('comments')->constrain(
                static fn (
                    Builder $q,
                    string $body,
                ) => $q->getQuery()->where('body', $body),
            );
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        QueryParameters::fromArray($query, [])->builder(context: $context);
    }

    /**
     * @return iterable<string, array{
     *     array<string, array<string, mixed>>,
     *     string,
     * }>
     */
    public static function unknownContextKeys(): iterable
    {
        yield 'undeclared relation' => [
            ['with' => ['ghost' => []]],
            'Relation ghost is not declared.',
        ];

        yield 'undeclared count' => [
            ['count' => ['ghost' => []]],
            'Count ghost is not declared.',
        ];

        yield 'unknown relation argument' => [
            ['with' => ['comments' => ['body' => 'a', 'ghost' => 1]]],
            'Relation comments has no context argument ghost.',
        ];

        yield 'unknown count argument' => [
            ['count' => ['comments' => ['body' => 'a', 'ghost' => 1]]],
            'Count comments has no context argument ghost.',
        ];

        yield 'relation without constraint' => [
            ['with' => ['tags' => ['body' => 'a']]],
            'Relation tags has no context argument body.',
        ];

        yield 'variadic parameter' => [
            ['with' => ['authorComments' => ['rest' => 'a']]],
            'Relation authorComments has no context argument rest.',
        ];

        yield 'first parameter' => [
            ['with' => ['comments' => ['q' => 1]]],
            'Relation comments has no context argument q.',
        ];
    }

    private static function loaded(string $body): Closure
    {
        return static fn (HasMany $q) => $q->getBaseQuery()->where(
            'body',
            $body,
        );
    }

    private static function counted(string $body): Closure
    {
        return static fn (Builder $q) => $q->getQuery()->where('body', $body);
    }

    private function bodyQuery(): ConfigurableQuery
    {
        return $this->configure(
            static function (RelationInterface $r): void {
                $r->relation('comments')->constrain(
                    static fn (
                        HasMany $q,
                        string $body,
                    ) => $q->getBaseQuery()->where('body', $body),
                );
                $r->count('comments')->constrain(
                    static fn (
                        Builder $q,
                        string $body,
                    ) => $q->getQuery()->where('body', $body),
                );
            },
            static function (SortInterface $s): void {
                $s->sort('comments')->count('comments');
            },
        );
    }

    private function countQuery(): ConfigurableQuery
    {
        return $this->configure(
            static function (RelationInterface $r): void {
                $r->count('comments')->constrain(
                    self::counted('a'),
                );
            },
            static function (SortInterface $s): void {
                $s->sort('comments')->count('comments');
            },
        );
    }

    /**
     * @param null|(Closure(RelationInterface): void) $relation
     * @param null|(Closure(SortInterface): void) $sort
     */
    private function configure(
        ?Closure $relation,
        ?Closure $sort = null,
    ): ConfigurableQuery {
        return new ConfigurableQuery(relation: $relation, sort: $sort);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array<string, mixed>> $context
     *
     * @return list<array<string, mixed>>
     */
    private function rows(
        ConfigurableQuery $query,
        array $input,
        array $context = [],
    ): array {
        /** @var list<array<string, mixed>> */
        return QueryParameters::fromArray($query, $input)
            ->builder(context: $context)
            ->get()
            ->toArray();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function comments(
        array $rows,
        string $relation = 'comments',
    ): array {
        $comments = [];
        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $loaded */
            $loaded = $row[$relation];
            self::assertIsString($row['title']);
            $comments[$row['title']] = $loaded;
        }

        return $comments;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, list<mixed>>
     */
    private function bodies(
        array $rows,
        string $relation = 'comments',
    ): array {
        return array_map(
            static fn (array $comments): array => array_column(
                $comments,
                'body',
            ),
            $this->comments($rows, $relation),
        );
    }
}
