<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeTraverser;

/**
 * Finds what keeps a configuration method from being a flat list of calls.
 */
final class FlatConfigurationAnalyser
{
    /**
     * @return list<int> line of each offending statement or expression
     */
    public function violations(ClassMethod $method): array
    {
        $name = $method->params[0]->var ?? null;

        if (!$name instanceof Variable) {
            return [];
        }

        $bound = [$name->name];
        $lines = [];

        $stmts = array_filter(
            $method->stmts ?? [],
            static fn (Node $stmt): bool => !$stmt instanceof Nop,
        );

        foreach ($stmts as $stmt) {
            $expr = $stmt instanceof Expression ? $stmt->expr : null;
            $target = null;

            if ($expr instanceof Assign) {
                $target = $expr->var;
                $expr = $expr->expr;
            }

            $args = $expr instanceof MethodCall
                ? $this->chainArguments($expr, $bound)
                : null;

            $plain = !$target instanceof Expr || $this->isLocal($target);

            if ($args === null || !$plain) {
                $lines[$stmt->getStartLine()] = null;

                continue;
            }

            foreach ($args as $line) {
                $lines[$line] = null;
            }

            if ($target instanceof Variable) {
                $bound[] = $target->name;
            }
        }

        return array_keys($lines);
    }

    private function isLocal(Expr $expr): bool
    {
        return $expr instanceof Variable;
    }

    /**
     * @param list<Expr|string> $bound
     *
     * @return null|list<int> lines of the misused objects, null when the
     *                        chain does not start from a bound variable
     */
    private function chainArguments(MethodCall $call, array $bound): ?array
    {
        $lines = [];
        $node = $call;

        while ($node instanceof MethodCall) {
            foreach ($node->args as $arg) {
                foreach ($this->misuses($arg, $bound) as $line) {
                    $lines[] = $line;
                }
            }

            $node = $node->var;
        }

        $root = $node instanceof Variable ? $node->name : null;

        return \in_array($root, $bound, true) ? $lines : null;
    }

    /**
     * @param list<Expr|string> $bound
     *
     * @return list<int>
     */
    private function misuses(Node $arg, array $bound): array
    {
        $visitor = new MisusedConfigurationVisitor($bound);

        new NodeTraverser($visitor)->traverse([$arg]);

        return $visitor->lines;
    }
}
