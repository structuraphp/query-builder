<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StructuraPhp\QueryBuilder\Scramble\NumberBoundsExtension;

#[CoversClass(NumberBoundsExtension::class)]
final class NumberBoundsExtensionTest extends TestCase
{
    public function testDropsTheBoundsOfAFloat(): void
    {
        $item = new NumberType();
        $item->min = -PHP_FLOAT_MAX;
        $item->max = PHP_FLOAT_MAX;

        $list = new ArrayType();
        $list->items = $item;

        $operation = $this->operation($this->parameter('rating', $list));

        $this->handle($operation);

        self::assertSame(
            ['type' => 'array', 'items' => ['type' => 'number']],
            $this->parameterAt($operation, 0)->schema?->toArray(),
        );
    }

    public function testDropsTheMaximumOfAPage(): void
    {
        $page = new IntegerType();
        $page->min = 1;
        $page->max = 92233720368547760;

        $operation = $this->operation($this->parameter('page', $page));

        $this->handle($operation);

        self::assertSame(
            ['type' => 'integer', 'minimum' => 1],
            $this->parameterAt($operation, 0)->schema?->toArray(),
        );
    }

    public function testKeepsTheBoundsAJsonNumberHolds(): void
    {
        $limit = new IntegerType();
        $limit->min = 1;
        $limit->max = 100;

        $operation = $this->operation($this->parameter('limit', $limit));

        $this->handle($operation);

        self::assertSame(
            ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            $this->parameterAt($operation, 0)->schema?->toArray(),
        );
    }

    #[DataProvider('limits')]
    public function testDropsOnlyTheBoundBeyondTheSafeIntegers(
        float|int $min,
        float|int $max,
        bool $keepsMin,
        bool $keepsMax,
    ): void {
        $number = new NumberType();
        $number->min = $min;
        $number->max = $max;

        $operation = $this->operation($this->parameter('value', $number));

        $this->handle($operation);

        self::assertSame(
            $keepsMin ? $min : null,
            $number->min,
        );
        self::assertSame(
            $keepsMax ? $max : null,
            $number->max,
        );
    }

    /**
     * @return iterable<string, array{float|int, float|int, bool, bool}>
     */
    public static function limits(): iterable
    {
        $safe = 9007199254740991;

        yield 'safe' => [-$safe, $safe, true, true];

        yield 'one past' => [-$safe - 1, $safe + 1, false, false];

        yield 'only the maximum' => [-5.5, 1e300, true, false];

        yield 'only the minimum' => [-1e300, 5.5, false, true];
    }

    public function testLeavesOtherParametersAlone(): void
    {
        $string = new StringType();
        $reference = new Reference('parameters', 'Shared', new Components());
        $bare = new Parameter('bare', 'query');

        $page = new IntegerType();
        $page->max = 92233720368547760;

        $operation = $this->operation(
            $this->parameter('title', $string),
            $reference,
            $bare,
            $this->parameter('page', $page),
        );

        $this->handle($operation);

        self::assertSame(
            [$operation->parameters[0], $reference, $bare],
            array_slice($operation->parameters, 0, 3),
        );
        self::assertNull($page->max);
        self::assertSame(
            ['type' => 'string'],
            $this->parameterAt($operation, 0)->schema?->toArray(),
        );
    }

    private function handle(Operation $operation): void
    {
        new NumberBoundsExtension(
            self::createStub(Infer::class),
            self::createStub(TypeTransformer::class),
            self::createStub(GeneratorConfig::class),
        )->handle($operation, self::createStub(RouteInfo::class));
    }

    private function operation(Parameter|Reference ...$parameters): Operation
    {
        $operation = new Operation('get');
        $operation->parameters = $parameters;

        return $operation;
    }

    private function parameter(string $name, Type $type): Parameter
    {
        $schema = new Schema();
        $schema->type = $type;

        $parameter = new Parameter($name, 'query');
        $parameter->schema = $schema;

        return $parameter;
    }

    private function parameterAt(Operation $operation, int $index): Parameter
    {
        $parameter = $operation->parameters[$index] ?? null;
        self::assertInstanceOf(Parameter::class, $parameter);

        return $parameter;
    }
}
