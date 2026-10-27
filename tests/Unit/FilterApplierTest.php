<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Enum\Mode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Service\FilterApplier;
use StructuraPhp\QueryBuilder\Service\TypeCaster;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class FilterApplierTest extends TestCase
{
    private const array COMPARISONS = [
        Mode::Equal,
        Mode::GreaterThan,
        Mode::LessThan,
        Mode::GreaterOrEqual,
        Mode::LessOrEqual,
    ];

    /**
     * @param list<Mode> $modes
     */
    #[DataProvider('types')]
    public function testModes(Type $type, array $modes): void
    {
        $actual = FilterApplier::modes($type);

        self::assertSame($modes, $actual);
    }

    /**
     * @return iterable<string, array{Type, list<Mode>}>
     */
    public static function types(): iterable
    {
        yield 'string' => [Type::String, [Mode::Equal, Mode::Like, Mode::In]];

        yield 'integer' => [Type::Integer, [...self::COMPARISONS, Mode::In]];

        yield 'float' => [Type::Float, [...self::COMPARISONS, Mode::In]];

        yield 'boolean' => [Type::Boolean, [Mode::Equal]];

        yield 'date' => [Type::Date, self::COMPARISONS];
    }

    #[DataProvider('comparisons')]
    public function testOperator(Mode $mode, string $operator): void
    {
        $actual = FilterApplier::operator($mode);

        self::assertSame($operator, $actual);
    }

    /**
     * @return iterable<string, array{Mode, string}>
     */
    public static function comparisons(): iterable
    {
        yield 'eq' => [Mode::Equal, '='];

        yield 'gt' => [Mode::GreaterThan, '>'];

        yield 'lt' => [Mode::LessThan, '<'];

        yield 'gte' => [Mode::GreaterOrEqual, '>='];

        yield 'lte' => [Mode::LessOrEqual, '<='];
    }

    #[DataProvider('nonComparisons')]
    public function testNonComparisonHasNoOperator(Mode $mode): void
    {
        $this->expectExceptionObject(new LogicException(
            "Mode {$mode->value} is not a comparison.",
        ));

        FilterApplier::operator($mode);
    }

    /**
     * @return iterable<string, array{Mode}>
     */
    public static function nonComparisons(): iterable
    {
        yield 'like' => [Mode::Like];

        yield 'in' => [Mode::In];

        yield 'null' => [Mode::IsNull];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('likeValues')]
    public function testLikeEscapesWildcards(
        string $value,
        array $expected,
    ): void {
        DB::table('articles')->insert(
            array_map(static fn (string $t): array => ['title' => $t], [
                '100%',
                '100 points',
                'a_b',
                'axb',
                'a!b',
            ]),
        );
        $query = Article::query();

        FilterApplier::apply($query, Type::String, 'title', Mode::Like, $value);
        $titles = $query->pluck('title')->all();

        self::assertSame($expected, $titles);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function likeValues(): iterable
    {
        yield 'percent' => ['100%', ['100%']];

        yield 'underscore' => ['a_b', ['a_b']];

        yield 'case insensitive' => ['A_B', ['a_b']];

        yield 'bang' => ['a!b', ['a!b']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('dateModes')]
    public function testDateComparesByDay(Mode $mode, array $expected): void
    {
        DB::table('articles')->insert([
            ['title' => 'A', 'created_at' => '2024-01-01 23:59:59'],
            ['title' => 'B', 'created_at' => '2024-01-02 00:00:00'],
            ['title' => 'C', 'created_at' => '2024-01-02 15:00:00'],
            ['title' => 'D', 'created_at' => '2024-01-03 00:00:00'],
        ]);
        $query = Article::query();
        $date = TypeCaster::cast(Type::Date, '2024-01-02');

        FilterApplier::apply($query, Type::Date, 'created_at', $mode, $date);
        $titles = $query->pluck('title')->sort()->values()->all();

        self::assertSame($expected, $titles);
    }

    /**
     * @return iterable<string, array{Mode, list<string>}>
     */
    public static function dateModes(): iterable
    {
        yield '=' => [Mode::Equal, ['B', 'C']];

        yield '>' => [Mode::GreaterThan, ['D']];

        yield '>=' => [Mode::GreaterOrEqual, ['B', 'C', 'D']];

        yield '<' => [Mode::LessThan, ['A']];

        yield '<=' => [Mode::LessOrEqual, ['A', 'B', 'C']];
    }

    /**
     * SQL Server reads `[` as the start of a character class.
     */
    public function testLikeEscapesBrackets(): void
    {
        $query = Article::query();

        FilterApplier::apply($query, Type::String, 'title', Mode::Like, 'a[b');

        self::assertSame(['%a![b%'], $query->getQuery()->getBindings());
    }

    public function testDateKeepsTheColumnIndexable(): void
    {
        $query = Article::query();
        $date = TypeCaster::cast(Type::Date, '2024-01-02');

        FilterApplier::apply(
            $query,
            Type::Date,
            'created_at',
            Mode::Equal,
            $date,
        );
        $base = $query->getQuery();

        $plain = Article::query()
            ->where('created_at', '>=', '2024-01-02')
            ->where('created_at', '<', '2024-01-03')
            ->getQuery();

        self::assertSame($plain->toSql(), $base->toSql());
        self::assertSame(['2024-01-02', '2024-01-03'], $base->getBindings());
    }

    public function testComparison(): void
    {
        DB::table('articles')->insert([
            ['title' => 'A', 'views' => 10],
            ['title' => 'B', 'views' => 200],
        ]);
        $query = Article::query();

        FilterApplier::apply(
            $query,
            Type::Integer,
            'views',
            Mode::GreaterThan,
            100,
        );
        $titles = $query->pluck('title')->all();

        self::assertSame(['B'], $titles);
    }

    public function testInWrapsASingleValue(): void
    {
        DB::table('articles')->insert([
            ['title' => 'A', 'views' => 10],
            ['title' => 'B', 'views' => 200],
        ]);
        $query = Article::query();

        FilterApplier::apply($query, Type::Integer, 'views', Mode::In, 200);
        $titles = $query->pluck('title')->all();

        self::assertSame(['B'], $titles);
    }

    public function testDateComparesAnUncastValueAsIs(): void
    {
        $query = Article::query();

        FilterApplier::apply(
            $query,
            Type::Date,
            'published_at',
            Mode::Equal,
            '2024-01-02',
        );
        $base = $query->getQuery();

        $plain = Article::query()
            ->where('published_at', '2024-01-02')
            ->getQuery();

        self::assertSame($plain->toSql(), $base->toSql());
        self::assertSame(['2024-01-02'], $base->getBindings());
    }
}
