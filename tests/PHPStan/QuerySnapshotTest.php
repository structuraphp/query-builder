<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\PHPStan\ConfigurationCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryClassCollector;
use StructuraPhp\QueryBuilder\PHPStan\QuerySnapshot;
use StructuraPhp\QueryBuilder\PHPStan\QuerySnapshotFactory;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;
use UnitEnum;

/**
 * Each query class gives an error holding its snapshot as JSON.
 *
 * @extends RuleTestCase<Rule<CollectedDataNode>>
 */
final class QuerySnapshotTest extends RuleTestCase
{
    private const array UNKNOWN = ['kind' => 'unknown'];

    private const string FILE = __DIR__ . '/data/collected-configuration.php';

    public function testEveryCallIsKept(): void
    {
        $errors = $this->gatherAnalyserErrors([self::FILE]);

        self::assertCount(1, $errors);
        self::assertSame([
            'model' => Article::class,
            'filters' => [
                $this->entity($this->call('filter', 45, ['views']), [
                    $this->call('type', 45, [$this->case(Type::Integer)]),
                    $this->call('maxitems', 45, [$this->int(3)]),
                ]),
            ],
            'scopes' => [
                $this->entity($this->call('scope', 51, ['near']), [
                    $this->call('cast', 52, [
                        'lat' => $this->case(Type::Float),
                        'lng' => ['kind' => 'closure'],
                    ]),
                ]),
                $this->entity($this->call('scope', 53, ['ofStatuses']), [
                    $this->call('rules', 54, [
                        'statuses' => $this->strings('max:2'),
                    ]),
                    $this->call('each', 55, [self::UNKNOWN]),
                ]),
                $this->entity($this->call('scope', 56, ['ofStatus']), [
                    $this->call('cast', 56, [
                        'status' => [
                            'kind' => 'class',
                            'value' => Status::class,
                        ],
                    ]),
                ]),
                $this->entity($this->call('scope', 57, ['tapped']), [
                    $this->call('default', 57, ['callback' => self::UNKNOWN]),
                ]),
            ],
            'sorts' => [
                $this->entity($this->call('sort', 62, ['date']), [
                    $this->call('column', 62, ['created_at']),
                ]),
            ],
            'defaultSorts' => [
                $this->call('default', 63, [
                    'date',
                    $this->case(Direction::Desc),
                ]),
                $this->call('default', 64, [
                    'name' => 'date',
                    'direction' => self::UNKNOWN,
                ]),
            ],
            'relations' => [
                $this->entity($this->call('relation', 69, ['author']), [
                    $this->call('fields', 69, [$this->strings('id', 'name')]),
                ]),
                $this->entity($this->call('relation', 70, ['comments']), [
                    $this->call('fields', 71, [self::UNKNOWN]),
                ]),
            ],
            'counts' => [
                $this->entity($this->call('count', 72, ['comments']), [
                    $this->call('constrain', 72, [['kind' => 'closure']]),
                ]),
            ],
            'pagination' => [
                $this->call('allow', 77, [
                    $this->case(PaginationMode::Offset),
                ]),
                $this->call('limit', 77, [$this->int(10)]),
                $this->call('maxlimit', 78, [self::UNKNOWN]),
            ],
        ], json_decode($errors[0]->getMessage(), true));
    }

    public function testCountIsAnEntityWithItsConstraint(): void
    {
        $errors = $this->gatherAnalyserErrors([self::FILE]);
        $counts = [
            $this->entity($this->call('count', 72, ['comments']), [
                $this->call('constrain', 72, [['kind' => 'closure']]),
            ]),
        ];

        self::assertStringContainsString(
            '"counts":' . json_encode($counts),
            $errors[0]->getMessage(),
        );
    }

    public function testPositionWinsOverName(): void
    {
        $first = ['kind' => 'string', 'value' => 'first'];
        $second = ['kind' => 'string', 'value' => 'second'];
        $call = [
            'method' => 'scope',
            'file' => self::FILE,
            'line' => 1,
            'args' => [0 => $first, 'name' => $second],
        ];

        self::assertSame('first', QuerySnapshot::string($call, 0, 'name'));
        self::assertSame('second', QuerySnapshot::string($call, 1, 'name'));
        self::assertNull(QuerySnapshot::string($call, 1, 'other'));
    }

    public function testArgumentsAreReadByKind(): void
    {
        $call = [
            'method' => 'limit',
            'file' => self::FILE,
            'line' => 1,
            'args' => [
                0 => ['kind' => 'int', 'value' => 5],
                1 => ['kind' => 'string', 'value' => 'text'],
                2 => ['kind' => 'class', 'value' => 'Some\Name'],
                3 => ['kind' => 'case', 'value' => 'A::B'],
                'mode' => ['kind' => 'case', 'value' => 'C::D'],
                'size' => ['kind' => 'int', 'value' => 7],
            ],
        ];

        self::assertSame(5, QuerySnapshot::int($call, 0, 'size'));
        self::assertSame(7, QuerySnapshot::int($call, 9, 'size'));
        self::assertNull(QuerySnapshot::int($call, 1, 'x'));
        self::assertSame('text', QuerySnapshot::string($call, 1, 'x'));
        self::assertSame('Some\Name', QuerySnapshot::string($call, 2, 'x'));
        self::assertNull(QuerySnapshot::string($call, 0, 'x'));
        self::assertNull(QuerySnapshot::string($call, 3, 'x'));
        self::assertSame('A::B', QuerySnapshot::case($call, 3, 'x'));
        self::assertSame('C::D', QuerySnapshot::case($call, 9, 'mode'));
        self::assertNull(QuerySnapshot::case($call, 1, 'x'));
        self::assertNull(QuerySnapshot::case($call, 9, 'x'));
    }

    protected function getRule(): Rule
    {
        $reflection = $this->createReflectionProvider();

        return new /**
         * @implements Rule<CollectedDataNode>
         */ readonly class($reflection) implements Rule {
            public function __construct(
                private ReflectionProvider $reflection,
            ) {}

            public function getNodeType(): string
            {
                return CollectedDataNode::class;
            }

            public function processNode(Node $node, Scope $scope): array
            {
                $methods = [];
                foreach ($node->get(ConfigurationCollector::class) as $data) {
                    foreach ($data as $method) {
                        $methods[$method['class']][$method['method']] = $method;
                    }
                }

                $errors = [];
                foreach ($node->get(QueryClassCollector::class) as $classes) {
                    foreach ($classes as $class) {
                        $snapshot = QuerySnapshotFactory::create(
                            $this->reflection->getClass($class),
                            $methods,
                        );
                        $errors[] = RuleErrorBuilder::message(
                            (string) json_encode($snapshot),
                        )->identifier('test.snapshot')->line(1)->build();
                    }
                }

                return $errors;
            }
        };
    }

    #[Override]
    protected function getCollectors(): array
    {
        return [new QueryClassCollector(), new ConfigurationCollector()];
    }

    /**
     * @param array<int|string, array<string, mixed>|string> $args a string
     *                                                             is a name
     *
     * @return array<string, mixed>
     */
    private function call(string $method, int $line, array $args): array
    {
        return [
            'method' => $method,
            'file' => self::FILE,
            'line' => $line,
            'args' => array_map(
                static fn (array|string $arg): array => \is_string($arg)
                    ? ['kind' => 'string', 'value' => $arg]
                    : $arg,
                $args,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @param list<array<string, mixed>> $options
     *
     * @return array<string, mixed>
     */
    private function entity(array $call, array $options): array
    {
        return ['call' => $call, 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    private function case(UnitEnum $case): array
    {
        return [
            'kind' => 'case',
            'value' => $case::class . '::' . $case->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function int(int $value): array
    {
        return ['kind' => 'int', 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function strings(string ...$values): array
    {
        return ['kind' => 'strings', 'value' => $values];
    }
}
