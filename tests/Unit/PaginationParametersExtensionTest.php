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
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use StructuraPhp\QueryBuilder\Scramble\PaginationParametersExtension;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleController;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\CursorArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\OffsetOnlyArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\PlainArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\UnpagedArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;

#[CoversClass(PaginationParametersExtension::class)]
final class PaginationParametersExtensionTest extends TestCase
{
    public function testDocumentsTheDefaultsOfTheQuery(): void
    {
        TestQueryRequest::$queryClass = PlainArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, $this->action());

        self::assertSame(
            ['with', 'pagination', 'limit', 'page', 'cursor', 'count'],
            $this->names($operation),
        );
        self::assertSame(
            [
                'type' => 'string',
                'default' => 'offset',
            ],
            $this->schema($operation, 'pagination'),
        );
        self::assertSame(
            [
                'type' => 'integer',
                'default' => 50,
            ],
            $this->schema($operation, 'limit'),
        );
        self::assertSame(
            [
                'type' => 'integer',
                'default' => 1,
            ],
            $this->schema($operation, 'page'),
        );
        self::assertSame(
            [
                'type' => 'string',
            ],
            $this->schema($operation, 'cursor'),
        );
        self::assertSame(
            [
                'with' => '',
                'pagination' => 'How the results are paginated.',
                'limit' => 'Number of results per page.',
                'page' => 'Page to read, in the `offset` pagination.',
                'cursor' => 'Cursor of the page to read, in the `cursor`'
                    . ' pagination: the one the previous page returned.',
                'count' => '',
            ],
            $this->descriptions($operation),
        );
    }

    public function testDocumentsTheConfiguredDefaults(): void
    {
        TestQueryRequest::$queryClass = CursorArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, $this->action());

        self::assertSame(
            'cursor',
            $this->schema($operation, 'pagination')['default'],
        );
        self::assertSame(10, $this->schema($operation, 'limit')['default']);
    }

    public function testRemovesThePageWithoutTheCursorMode(): void
    {
        TestQueryRequest::$queryClass = OffsetOnlyArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, $this->action());

        self::assertSame(
            ['with', 'pagination', 'limit', 'page', 'count'],
            $this->names($operation),
        );
        self::assertSame(20, $this->schema($operation, 'limit')['default']);
    }

    public function testRemovesWhatOnlyAPageNeeds(): void
    {
        TestQueryRequest::$queryClass = UnpagedArticleQuery::class;
        $operation = $this->operation();

        $this->handle($operation, $this->action());

        self::assertSame(
            ['with', 'pagination', 'count'],
            $this->names($operation),
        );
        self::assertSame(
            'none',
            $this->schema($operation, 'pagination')['default'],
        );
    }

    public function testLeavesAnotherActionAlone(): void
    {
        $operation = $this->operation();

        $this->handle($operation, new ReflectionMethod(
            self::class,
            'testLeavesAnotherActionAlone',
        ));

        self::assertSame(
            ['with', 'pagination', 'limit', 'page', 'cursor', 'count'],
            $this->names($operation),
        );
        self::assertSame(
            ['type' => 'integer'],
            $this->schema($operation, 'page'),
        );
    }

    private function action(): ReflectionMethod
    {
        return new ReflectionMethod(ArticleController::class, 'index');
    }

    private function handle(
        Operation $operation,
        ReflectionMethod $action,
    ): void {
        $route = self::createStub(RouteInfo::class);
        $route->method('reflectionMethod')->willReturn($action);

        new PaginationParametersExtension(
            self::createStub(Infer::class),
            self::createStub(TypeTransformer::class),
            self::createStub(GeneratorConfig::class),
        )->handle($operation, $route);
    }

    private function operation(): Operation
    {
        $operation = new Operation('get');
        $operation->parameters = [
            $this->query('with', new StringType()),
            $this->query('pagination', new StringType()),
            $this->query('limit', new IntegerType()),
            $this->query('page', new IntegerType()),
            $this->query('cursor', new StringType()),
            $this->query('count', new StringType()),
        ];

        return $operation;
    }

    private function query(string $name, Type $type): Parameter
    {
        $schema = new Schema();
        $schema->type = $type;

        $parameter = new Parameter($name, 'query');
        $parameter->schema = $schema;

        return $parameter;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function schema(Operation $operation, string $name): array
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->name === $name) {
                $schema = $parameter->schema?->toArray();
                self::assertIsArray($schema);

                return $schema;
            }
        }

        self::fail("No parameter {$name}.");
    }

    /**
     * @return array<string, string>
     */
    private function descriptions(Operation $operation): array
    {
        $descriptions = [];

        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter) {
                $descriptions[$parameter->name] = $parameter->description;
            }
        }

        return $descriptions;
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
