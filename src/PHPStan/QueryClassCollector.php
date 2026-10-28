<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;

/**
 * Collects the query classes: the concrete classes that implement FromModel.
 *
 * @implements Collector<InClassNode, class-string>
 */
final class QueryClassCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?string
    {
        $class = $node->getClassReflection();

        // An anonymous class is only known to the worker that found it.
        return !$class->isAbstract()
            && !$class->isAnonymous()
            && $class->implementsInterface(FromModel::class)
            ? $class->getName()
            : null;
    }
}
