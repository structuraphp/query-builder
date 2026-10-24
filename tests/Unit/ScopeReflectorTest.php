<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Validation\Rules\Enum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\Service\ScopeReflector;
use StructuraPhp\QueryBuilder\ValueObject\Config\ScopeConfig;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class ScopeReflectorTest extends TestCase
{
    public function testNoArgumentScope(): void
    {
        $arguments = $this->arguments(new ScopeConfig('popular'));

        self::assertSame([], $arguments);
    }

    public function testDateArguments(): void
    {
        [$from, $to] = $this->arguments(new ScopeConfig('publishedBetween'));
        $cast = ($from->cast)('2024-01-02');

        self::assertSame('from', $from->name);
        self::assertSame('to', $to->name);
        self::assertSame(['date_format:Y-m-d'], $from->rules);
        self::assertNull($from->default);
        self::assertInstanceOf(CarbonImmutable::class, $cast);
    }

    public function testEnumArgument(): void
    {
        [$status] = $this->arguments(new ScopeConfig('ofStatus'));
        $cast = ($status->cast)('draft');

        self::assertInstanceOf(Enum::class, $status->rules[0]);
        self::assertSame(Status::Draft, $cast);
    }

    public function testArrayArgument(): void
    {
        [$statuses] = $this->arguments(new ScopeConfig('ofStatuses'));
        $cast = ($statuses->cast)(['draft']);

        self::assertSame(['array'], $statuses->rules);
        self::assertSame(['draft'], $cast);
    }

    public function testMutableCarbonArgument(): void
    {
        [$date] = $this->arguments(new ScopeConfig('since'));
        $cast = ($date->cast)('2024-01-02');

        self::assertInstanceOf(Carbon::class, $cast);
    }

    public function testAttributeScopeAndPhpDefault(): void
    {
        [$min] = $this->arguments(new ScopeConfig('minViews'));
        $default = $min->default instanceof Closure ? ($min->default)() : null;
        $cast = ($min->cast)('12');

        self::assertSame(['integer'], $min->rules);
        self::assertSame(10, $default);
        self::assertSame(12, $cast);
    }

    public function testConfigDefaultWinsOverPhpDefault(): void
    {
        $config = new ScopeConfig('minViews')->default(min: fn (): int => 50);

        [$min] = $this->arguments($config);
        $default = $min->default instanceof Closure ? ($min->default)() : null;

        self::assertSame(50, $default);
    }

    public function testFixedConfigDefault(): void
    {
        $config = new ScopeConfig('publishedBetween')->default(to: 'fixed');

        [, $to] = $this->arguments($config);
        $default = $to->default instanceof Closure ? ($to->default)() : null;

        self::assertSame('fixed', $default);
    }

    public function testMutableDateDefaultIsConverted(): void
    {
        $config = new ScopeConfig('publishedBetween')->default(
            to: fn (): CarbonInterface => now(),
        );

        [, $to] = $this->arguments($config);
        $default = $to->default instanceof Closure ? ($to->default)() : null;

        self::assertInstanceOf(CarbonImmutable::class, $default);
    }

    public function testMethodAlias(): void
    {
        $config = new ScopeConfig('statut')->method('ofStatus');

        [$status] = $this->arguments($config);

        self::assertSame('status', $status->name);
    }

    public function testForcedCast(): void
    {
        $config = new ScopeConfig('near')->cast(
            lat: Type::Float,
            lng: Type::Float,
        );

        [$lat] = $this->arguments($config);
        $cast = ($lat->cast)('1.5');

        self::assertSame(['numeric'], $lat->rules);
        self::assertSame(1.5, $cast);
    }

    public function testForcedEnumCast(): void
    {
        $config = new ScopeConfig('near')->cast(
            lat: Status::class,
            lng: Type::Float,
        );

        [$lat] = $this->arguments($config);
        $cast = ($lat->cast)('published');

        self::assertSame(Status::Published, $cast);
    }

    public function testCustomRulesEachMessagesAttribute(): void
    {
        $config = new ScopeConfig('ofStatuses')
            ->rules(statuses: ['max:2'])
            ->each(statuses: ['in:draft,published'])
            ->messages(statuses: ['max' => 'Trop.'])
            ->attribute(statuses: 'statuts');

        [$statuses] = $this->arguments($config);

        self::assertSame(['array', 'max:2'], $statuses->rules);
        self::assertSame(['in:draft,published'], $statuses->each);
        self::assertSame(['max' => 'Trop.'], $statuses->messages);
        self::assertSame('statuts', $statuses->attribute);
    }

    public function testClosureCastFailureIsAValidationRule(): void
    {
        $config = new ScopeConfig('near')->cast(
            lat: fn (mixed $v) => throw new RuntimeException(),
            lng: Type::Float,
        );
        [$lat] = $this->arguments($config);
        $rule = $lat->rules[0];
        $failed = false;
        $fail = function () use (&$failed): object {
            $failed = true;

            return new class {
                public function translate(): void {}
            };
        };

        if ($rule instanceof Closure) {
            $rule('lat', 'x', $fail);
        }

        self::assertInstanceOf(Closure::class, $rule);
        self::assertTrue($failed);
    }

    /**
     * @param Closure(): ScopeConfig $config
     */
    #[DataProvider('invalidConfigs')]
    public function testConfigErrors(Closure $config): void
    {
        $this->expectException(ConfigurationException::class);

        $this->arguments($config());
    }

    /**
     * Configs are built lazily: some of them throw while being declared.
     *
     * @return iterable<string, array{Closure(): ScopeConfig}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown scope' => [
            fn (): ScopeConfig => new ScopeConfig('missing'),
        ];

        yield 'untyped without cast' => [
            fn (): ScopeConfig => new ScopeConfig('near'),
        ];

        yield 'unknown default argument' => [
            fn (): ScopeConfig => new ScopeConfig('publishedBetween')->default(
                foo: 1,
            ),
        ];

        yield 'unknown cast argument' => [
            fn (): ScopeConfig => new ScopeConfig('publishedBetween')->cast(
                foo: Type::String,
            ),
        ];

        yield 'positional rules' => [
            fn (): ScopeConfig => new ScopeConfig('ofStatuses')->rules([
                'max:2',
            ]),
        ];

        yield 'union type' => [fn (): ScopeConfig => new ScopeConfig('union')];

        yield 'cast to non enum class' => [
            fn (): ScopeConfig => new ScopeConfig('near')->cast(
                // @phpstan-ignore argument.type
                lat: stdClass::class,
                lng: Type::Float,
            ),
        ];
    }

    /**
     * @return list<ScopeArgument>
     */
    private function arguments(ScopeConfig $config): array
    {
        return new ScopeReflector()->arguments(new Article(), $config);
    }
}
