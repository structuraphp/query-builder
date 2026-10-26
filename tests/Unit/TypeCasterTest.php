<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Service\TypeCaster;
use UnexpectedValueException;

final class TypeCasterTest extends TestCase
{
    /**
     * @param list<string> $rules
     */
    #[DataProvider('types')]
    public function testRulesAndCast(
        Type $type,
        array $rules,
        mixed $input,
        mixed $expected,
    ): void {
        $actualRules = TypeCaster::rules($type);
        $cast = TypeCaster::cast($type, $input);

        self::assertSame($rules, $actualRules);
        self::assertSame($expected, $cast);
    }

    /**
     * @return iterable<string, array{Type, list<string>, mixed, mixed}>
     */
    public static function types(): iterable
    {
        yield 'string' => [Type::String, ['string'], 12, '12'];

        yield 'integer' => [Type::Integer, ['integer'], '12', 12];

        yield 'float' => [Type::Float, ['numeric'], '1.5', 1.5];

        yield 'boolean' => [Type::Boolean, ['boolean'], '0', false];
    }

    public function testDateRules(): void
    {
        $rules = TypeCaster::rules(Type::Date);

        self::assertSame(['date_format:Y-m-d'], $rules);
    }

    public function testDateCastIsMidnight(): void
    {
        $date = TypeCaster::cast(Type::Date, '2024-01-02');

        self::assertInstanceOf(CarbonImmutable::class, $date);
        self::assertSame('2024-01-02 00:00:00', $date->toDateTimeString());
    }

    public function testNonScalarIsABug(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException(
            'Expected a scalar, got stdClass.',
        ));

        TypeCaster::cast(Type::String, new stdClass());
    }
}
