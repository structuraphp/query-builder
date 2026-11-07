<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use StructuraPhp\QueryBuilder\Scramble\RelationParametersExtension;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleController;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\CountArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\PlainArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;

#[CoversClass(RelationParametersExtension::class)]
final class RelationParametersExtensionTest extends TestCase
{
    public function testDocumentsTheDeclaredRelations(): void
    {
        TestQueryRequest::$queryClass = CountArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, new ReflectionMethod(
            ArticleController::class,
            'index',
        ));

        $with = $this->parameter($operation, 'with');
        self::assertSame(
            'Relations to load, separated by commas: `author`,'
            . ' `author.articles`.',
            $with->description,
        );
        self::assertSame(
            [
                'type' => 'string',
                'pattern' => '^\s*(author|author\.articles)\s*'
                    . '(,\s*(author|author\.articles)\s*)*$',
            ],
            $with->schema?->toArray(),
        );

        $count = $this->parameter($operation, 'count');
        self::assertSame(
            'Relations to count, separated by commas: `comments`.',
            $count->description,
        );
        self::assertSame(
            [
                'type' => 'string',
                'pattern' => '^\s*(comments)\s*(,\s*(comments)\s*)*$',
            ],
            $count->schema?->toArray(),
        );

        self::assertSame(
            ['page', 'with', 'count', 'limit'],
            $this->names($operation),
        );
    }

    public function testRemovesWhatTheQueryDoesNotDeclare(): void
    {
        TestQueryRequest::$queryClass = PlainArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, new ReflectionMethod(
            ArticleController::class,
            'index',
        ));

        self::assertSame(['page', 'limit'], $this->names($operation));
    }

    public function testLeavesAnotherActionAlone(): void
    {
        $operation = $this->operation();

        $this->handle($operation, new ReflectionMethod(
            self::class,
            'testLeavesAnotherActionAlone',
        ));

        self::assertSame(
            ['page', 'with', 'count', 'limit'],
            $this->names($operation),
        );
        self::assertSame('', $this->parameter($operation, 'with')->description);
    }

    public function testReadsTheRequestAmongOtherParameters(): void
    {
        TestQueryRequest::$queryClass = CountArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, new ReflectionMethod(
            ArticleController::class,
            'mixed',
        ));

        self::assertSame(
            'Relations to count, separated by commas: `comments`.',
            $this->parameter($operation, 'count')->description,
        );
    }

    public function testLeavesAnActionWithoutReflectionAlone(): void
    {
        $operation = $this->operation();

        $this->handle($operation, null);

        self::assertSame(
            ['page', 'with', 'count', 'limit'],
            $this->names($operation),
        );
    }

    private function handle(
        Operation $operation,
        ?ReflectionMethod $action,
    ): void {
        $route = self::createStub(RouteInfo::class);
        $route->method('reflectionMethod')->willReturn($action);

        new RelationParametersExtension(
            self::createStub(Infer::class),
            self::createStub(TypeTransformer::class),
            self::createStub(GeneratorConfig::class),
        )->handle($operation, $route);
    }

    private function operation(): Operation
    {
        $operation = new Operation('get');
        $operation->parameters = [
            $this->query('page', new IntegerType()),
            $this->query('with', new StringType()),
            $this->query('count', new StringType()),
            $this->query('limit', new IntegerType()),
        ];

        return $operation;
    }

    private function query(
        string $name,
        IntegerType|StringType $type,
    ): Parameter {
        $schema = new Schema();
        $schema->type = $type;

        $parameter = new Parameter($name, 'query');
        $parameter->schema = $schema;

        return $parameter;
    }

    private function parameter(Operation $operation, string $name): Parameter
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->name === $name) {
                return $parameter;
            }
        }

        self::fail("No parameter {$name}.");
    }

    /**
     * @return list<string>
     */
    private function names(Operation $operation): array
    {
        return array_values(array_map(
            static fn (Parameter|Reference $parameter): string => $parameter
                instanceof Parameter ? $parameter->name : '$ref',
            $operation->parameters,
        ));
    }
}
