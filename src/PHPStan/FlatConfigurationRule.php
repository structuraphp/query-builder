<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class FlatConfigurationRule implements Rule
{
    private FlatConfigurationAnalyser $analyser;

    public function __construct()
    {
        $this->analyser = new FlatConfigurationAnalyser();
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();

        if (
            !$class instanceof ClassReflection
            || !ConfigurationMethods::isConfiguration(
                $class,
                $node->getMethodReflection()->getName(),
            )
        ) {
            return [];
        }

        $errors = [];

        $lines = $this->analyser->violations($node->getOriginalNode());

        foreach ($lines as $line) {
            $errors[] = RuleErrorBuilder::message(
                'Query configuration must be a flat list of calls: '
                . 'no condition, helper or parent call.',
            )
                ->identifier('structuraQueryBuilder.flatConfiguration')
                ->line($line)
                ->build();
        }

        return $errors;
    }
}
