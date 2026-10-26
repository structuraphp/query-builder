<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StructuraPhp\QueryBuilder\Enum\Mode;

final class ModeTest extends TestCase
{
    #[DataProvider('comparisons')]
    public function testOperator(Mode $mode, string $operator): void
    {
        $actual = $mode->operator();

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

        $mode->operator();
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
}
