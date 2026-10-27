<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;

/**
 * Collects the model getModel() returns, and the calls of each flat
 * configuration method.
 *
 * Calls are numbered from 1 in the order of the code: `target` is the
 * entity a call is made on (0 for the parameter, else the number of the
 * call that created it), `result` the entity it returns.
 *
 * @phpstan-import-type CollectedArg from QuerySnapshot
 *
 * @phpstan-type CollectedCall array{
 *     target: int,
 *     result: int,
 *     method: string,
 *     line: int,
 *     args: array<int|string, CollectedArg>,
 * }
 * @phpstan-type CollectedMethod array{
 *     class: string,
 *     method: string,
 *     file: string,
 *     model?: string,
 *     calls?: list<CollectedCall>,
 * }
 *
 * @implements Collector<InClassMethodNode, CollectedMethod>
 */
final readonly class ConfigurationCollector implements Collector
{
    /** Calls on the parameter that create an entity. */
    private const array CREATORS = [
        'filter',
        'scope',
        'sort',
        'relation',
        'count',
    ];

    private FlatConfigurationAnalyser $analyser;

    public function __construct()
    {
        $this->analyser = new FlatConfigurationAnalyser();
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $class = $node->getClassReflection();
        $method = $node->getOriginalNode();
        $name = $method->name->toLowerString();
        $data = [
            'class' => $class->getName(),
            'method' => $name,
            'file' => $scope->getFile(),
        ];

        if (
            $name === 'getmodel'
            && $class->implementsInterface(FromModel::class)
        ) {
            $model = $this->model($method, $scope);

            return $model === null ? null : [...$data, 'model' => $model];
        }

        if (
            !ConfigurationMethods::isConfiguration($class, $name)
            || $this->analyser->violations($method) !== []
        ) {
            return null;
        }

        $calls = $this->calls($method, $scope);

        return $calls === null ? null : [...$data, 'calls' => $calls];
    }

    /**
     * Only `return X::class;`, or another single constant class name.
     */
    private function model(ClassMethod $method, Scope $scope): ?string
    {
        $stmts = $method->stmts ?? [];
        $expr = \count($stmts) === 1 && $stmts[0] instanceof Return_
            ? $stmts[0]->expr
            : null;

        if (!$expr instanceof Expr) {
            return null;
        }

        $strings = $scope->getType($expr)->getConstantStrings();

        return \count($strings) === 1 && $strings[0]->isClassString()->yes()
            ? $strings[0]->getValue()
            : null;
    }

    /**
     * The method is flat: each statement is a chain of calls, maybe assigned
     * to a variable, that starts from the parameter or such a variable.
     *
     * @return null|list<CollectedCall> null when a call cannot be read
     */
    private function calls(ClassMethod $method, Scope $scope): ?array
    {
        /** @var Variable $parameter */
        $parameter = $method->params[0]->var;

        /** @var string $name */
        $name = $parameter->name;

        /** @var array<string, int> $held entity held by each variable */
        $held = [$name => 0];
        $calls = [];

        // Other statements are comments.
        $exprs = array_map(
            static fn (Expression $stmt): Expr => $stmt->expr,
            array_filter(
                $method->stmts ?? [],
                static fn (Stmt $stmt): bool => $stmt instanceof Expression,
            ),
        );

        foreach ($exprs as $expr) {
            $variable = null;

            if ($expr instanceof Assign) {
                /** @var Variable $variable */
                $variable = $expr->var;
                $expr = $expr->expr;
            }

            $chain = [];

            while ($expr instanceof MethodCall) {
                array_unshift($chain, $expr);
                $expr = $expr->var;
            }

            /** @var Variable $expr */
            /** @var string $root */
            $root = $expr->name;
            $entity = $held[$root];

            foreach ($chain as $call) {
                $index = \count($calls) + 1;
                $collected = $this->call($call, $entity, $index, $scope);

                if ($collected === null) {
                    return null;
                }

                $calls[] = $collected;
                $entity = $collected['result'];
            }

            if ($variable instanceof Variable) {
                /** @var string $local */
                $local = $variable->name;
                $held[$local] = $entity;
            }
        }

        return $calls;
    }

    /**
     * @return null|CollectedCall null for a dynamic name, an unpacked
     *                            argument or a first-class callable
     */
    private function call(
        MethodCall $call,
        int $target,
        int $index,
        Scope $scope,
    ): ?array {
        if (
            !$call->name instanceof Identifier
            || $call->isFirstClassCallable()
        ) {
            return null;
        }

        $method = $call->name->toLowerString();
        $args = [];

        foreach ($call->getArgs() as $position => $arg) {
            if ($arg->unpack) {
                return null;
            }

            $key = $arg->name?->toString() ?? $position;
            $args[$key] = $this->argument($scope->getType($arg->value));
        }

        // count() on a sort is one of its options.
        $creates = $target === 0
            && \in_array($method, self::CREATORS, true);

        return [
            'target' => $target,
            'result' => $creates ? $index : $target,
            'method' => $method,
            'line' => $call->name->getStartLine(),
            'args' => $args,
        ];
    }

    /**
     * @return CollectedArg
     */
    private function argument(Type $type): array
    {
        if (new ObjectType(Closure::class)->isSuperTypeOf($type)->yes()) {
            return ['kind' => 'closure'];
        }

        $cases = $type->getEnumCases();

        if (\count($cases) === 1) {
            return [
                'kind' => 'case',
                'value' => $cases[0]->getClassName()
                    . '::' . $cases[0]->getEnumCaseName(),
            ];
        }

        $strings = $type->getConstantStrings();

        if (\count($strings) === 1) {
            return [
                'kind' => $strings[0]->isClassString()->yes()
                    ? 'class'
                    : 'string',
                'value' => $strings[0]->getValue(),
            ];
        }

        $scalars = $type->getConstantScalarValues();

        if (\count($scalars) === 1 && \is_int($scalars[0])) {
            return ['kind' => 'int', 'value' => $scalars[0]];
        }

        $arrays = $type->getConstantArrays();

        if (\count($arrays) === 1) {
            return $this->strings($arrays[0]->getValueTypes());
        }

        return ['kind' => 'unknown'];
    }

    /**
     * @param array<Type> $types
     *
     * @return CollectedArg
     */
    private function strings(array $types): array
    {
        $values = [];

        foreach ($types as $type) {
            $strings = $type->getConstantStrings();

            if (\count($strings) !== 1) {
                return ['kind' => 'unknown'];
            }

            $values[] = $strings[0]->getValue();
        }

        return ['kind' => 'strings', 'value' => $values];
    }
}
