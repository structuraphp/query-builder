<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use StructuraPhp\QueryBuilder\Scramble\ScalarFilterValueExtension;

#[CoversClass(ScalarFilterValueExtension::class)]
final class ScalarFilterValueExtensionTest extends TestCase
{
    public function testAddsTheScalarValueJustBeforeTheList(): void
    {
        $statuses = ['pending', 'active'];
        $item = new StringType();
        $item->enum = $statuses;

        $array = new ArrayType();
        $array->items = $item;
        $array->enum = $statuses;

        $list = $this->parameter('status[value][]', $array);
        $list->example = [['active']];

        $operation = $this->operation(
            $this->parameter('page', new IntegerType()),
            $list,
            $this->parameter('status[mode]', new StringType()),
        );

        $this->handle($operation);

        self::assertSame(
            ['page', 'status[value]', 'status[value][]', 'status[mode]'],
            $this->names($operation),
        );
        self::assertSame(
            [
                'name' => 'status[value]',
                'in' => 'query',
                'schema' => ['type' => 'string', 'enum' => $statuses],
            ],
            $this->parameterAt($operation, 1)->toArray(),
        );
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => $statuses],
            ],
            $array->toArray(),
        );
    }

    public function testClonesTheItemSchemaOfTheList(): void
    {
        $operation = $this->operation(
            $this->listParameter('id[value][]', new IntegerType()),
            $this->parameter('id[mode]', new StringType()),
        );

        $this->handle($operation);

        $scalar = $this->schemaAt($operation, 0);
        $list = $this->schemaAt($operation, 1);
        $array = $list->type;
        self::assertInstanceOf(ArrayType::class, $array);
        self::assertNotSame($list, $scalar);
        self::assertNotSame($array->items, $scalar->type);
        self::assertEquals($array->items, $scalar->type);
    }

    public function testLeavesTheParametersOtherThanAFilterUntouched(): void
    {
        $reference = self::createStub(Reference::class);
        $parameters = [
            $reference,
            // A list without mode is not a filter value.
            $this->listParameter('labels[value][]', new StringType()),
            // A list of the filter which is not its value.
            $this->listParameter('tags[items][]', new StringType()),
            $this->parameter('tags[mode]', new StringType()),
            $this->listParameter('ids[]', new IntegerType()),
            $this->parameter('ids[mode]', new StringType()),
            $this->parameter('title[value]', new StringType()),
            $this->parameter('title[mode]', new StringType()),
            // A filter value which is not a list.
            $this->parameter('views[value][]', new IntegerType()),
            $this->parameter('views[mode]', new StringType()),
            new Parameter('date[value][]', 'query'),
            $this->parameter('date[mode]', new StringType()),
        ];
        $operation = $this->operation(...$parameters);

        $this->handle($operation);

        self::assertSame($parameters, $operation->parameters);
    }

    public function testRunsOnlyOnceOnTheSameOperation(): void
    {
        $operation = $this->operation(
            $this->listParameter('id[value][]', new IntegerType()),
            $this->parameter('id[mode]', new StringType()),
        );
        $this->handle($operation);
        $parameters = $operation->parameters;

        $this->handle($operation);

        self::assertSame($parameters, $operation->parameters);
    }

    private function handle(Operation $operation): void
    {
        new ScalarFilterValueExtension(
            self::createStub(Infer::class),
            self::createStub(TypeTransformer::class),
            self::createStub(GeneratorConfig::class),
        )->handle($operation, self::createStub(RouteInfo::class));
    }

    private function operation(
        Parameter|Reference ...$parameters,
    ): Operation {
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

    private function listParameter(string $name, Type $item): Parameter
    {
        $list = new ArrayType();
        $list->items = $item;

        return $this->parameter($name, $list);
    }

    private function parameterAt(
        Operation $operation,
        int $index,
    ): Parameter {
        $parameter = $operation->parameters[$index] ?? null;
        self::assertInstanceOf(Parameter::class, $parameter);

        return $parameter;
    }

    private function schemaAt(
        Operation $operation,
        int $index,
    ): Schema {
        $schema = $this->parameterAt($operation, $index)->schema;
        self::assertInstanceOf(Schema::class, $schema);

        return $schema;
    }

    /**
     * @return list<string>
     */
    private function names(Operation $operation): array
    {
        return array_map(
            static fn (Parameter|Reference $parameter): string => $parameter
                instanceof Parameter ? $parameter->name : '$ref',
            array_values($operation->parameters),
        );
    }
}
