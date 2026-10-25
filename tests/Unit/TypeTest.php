<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Enum\Mode;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class TypeTest extends TestCase
{
    private const array COMPARISONS = [
        Mode::Equal,
        Mode::GreaterThan,
        Mode::LessThan,
        Mode::GreaterOrEqual,
        Mode::LessOrEqual,
    ];

    /**
     * @param list<string> $rules
     * @param list<Mode> $modes
     */
    #[DataProvider('types')]
    public function testRulesModesAndCast(
        Type $type,
        array $rules,
        array $modes,
        mixed $input,
        mixed $expected,
    ): void {
        $actualRules = $type->rules();
        $actualModes = $type->modes();
        $cast = $type->cast($input);

        self::assertSame($rules, $actualRules);
        self::assertSame($modes, $actualModes);
        self::assertSame($expected, $cast);
    }

    /**
     * @return iterable<string, array{
     *     Type,
     *     list<string>,
     *     list<Mode>,
     *     mixed,
     *     mixed,
     * }>
     */
    public static function types(): iterable
    {
        yield 'string' => [
            Type::String,
            ['string'],
            [Mode::Equal, Mode::Like, Mode::In],
            12,
            '12',
        ];

        yield 'integer' => [
            Type::Integer,
            ['integer'],
            [...self::COMPARISONS, Mode::In],
            '12',
            12,
        ];

        yield 'float' => [
            Type::Float,
            ['numeric'],
            [...self::COMPARISONS, Mode::In],
            '1.5',
            1.5,
        ];

        yield 'boolean' => [
            Type::Boolean,
            ['boolean'],
            [Mode::Equal],
            '0',
            false,
        ];
    }

    public function testDateRulesAndModes(): void
    {
        $rules = Type::Date->rules();
        $modes = Type::Date->modes();

        self::assertSame(['date_format:Y-m-d'], $rules);
        self::assertSame(self::COMPARISONS, $modes);
    }

    public function testDateCastIsMidnight(): void
    {
        $date = Type::Date->cast('2024-01-02');

        self::assertInstanceOf(CarbonImmutable::class, $date);
        self::assertSame('2024-01-02 00:00:00', $date->toDateTimeString());
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
            array_map(fn (string $t): array => ['title' => $t], [
                '100%',
                '100 points',
                'a_b',
                'axb',
                'a!b',
            ]),
        );
        $query = Article::query();

        Type::String->apply($query, 'title', Mode::Like, $value);
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
        $date = Type::Date->cast('2024-01-02');

        Type::Date->apply($query, 'created_at', $mode, $date);
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

    public function testDateKeepsTheColumnIndexable(): void
    {
        $query = Article::query();
        $date = Type::Date->cast('2024-01-02');

        Type::Date->apply($query, 'created_at', Mode::Equal, $date);
        $base = $query->getQuery();

        self::assertSame(
            'select * from "articles" '
            . 'where "created_at" >= ? and "created_at" < ?',
            $base->toSql(),
        );
        self::assertSame(['2024-01-02', '2024-01-03'], $base->getBindings());
    }

    public function testComparison(): void
    {
        DB::table('articles')->insert([
            ['title' => 'A', 'views' => 10],
            ['title' => 'B', 'views' => 200],
        ]);
        $query = Article::query();

        Type::Integer->apply($query, 'views', Mode::GreaterThan, 100);
        $titles = $query->pluck('title')->all();

        self::assertSame(['B'], $titles);
    }
}
