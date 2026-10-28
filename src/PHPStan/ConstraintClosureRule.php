<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * Reports a constrain() closure whose first parameter cannot accept the
 * relation, or the builder, that ConstraintClosureTypeExtension gives it.
 *
 * @implements Rule<MethodCall>
 */
final readonly class ConstraintClosureRule implements Rule
{
    public function __construct(
        private ConstraintClosureTypeExtension $extension,
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $closure = $node->getRawArgs()[0]->value ?? null;

        if (
            !$closure instanceof Closure && !$closure instanceof ArrowFunction
            || !$node->name instanceof Identifier
        ) {
            return [];
        }

        $method = $scope->getMethodReflection(
            $scope->getType($node->var),
            $node->name->toString(),
        );

        if (
            !$method instanceof ExtendedMethodReflection
            || !$this->extension->supports($method)
        ) {
            return [];
        }

        $expected = $this->extension->expected($node, $scope);
        $given = $scope->getType($closure)
            ->getCallableParametersAcceptors($scope)[0]
            ->getParameters()[0] ?? null;

        // Only a type that can never be given: no false error.
        if (
            !$expected instanceof Type
            || $given === null
            || !$given->getType()->isSuperTypeOf($expected)->no()
        ) {
            return [];
        }

        $level = VerbosityLevel::getRecommendedLevelByType(
            $expected,
            $given->getType(),
        );

        return [
            RuleErrorBuilder::message(\sprintf(
                'Parameter $%s of the constrain() closure expects %s, '
                . '%s given.',
                $given->getName(),
                $given->getType()->describe($level),
                $expected->describe($level),
            ))
                ->identifier('argument.type')
                ->line($closure->getStartLine())
                ->build(),
        ];
    }
}
