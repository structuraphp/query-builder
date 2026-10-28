<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
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

        /** @var string $parameter a parameter has a plain name */
        $parameter = $name->name;
        $bound = [$parameter];
        $lines = [];

        foreach ($this->statements($method) as $stmt) {
            $flat = $this->statement($stmt, $bound);

            if ($flat === null) {
                $lines[$stmt->getStartLine()] = null;

                continue;
            }

            [$args, $local] = $flat;
            $lines += array_fill_keys($args, null);

            if ($local !== null) {
                $bound[] = $local;
            }
        }

        return array_keys($lines);
    }

    /**
     * @return array<Stmt>
     */
    private function statements(ClassMethod $method): array
    {
        $stmts = array_filter(
            $method->stmts ?? [],
            static fn (Node $stmt): bool => !$stmt instanceof Nop,
        );
        $last = end($stmts);

        // A bare return that ends the method changes nothing.
        if ($last instanceof Return_ && !$last->expr instanceof Expr) {
            array_pop($stmts);
        }

        return $stmts;
    }

    /**
     * @param list<string> $bound
     *
     * @return null|array{list<int>, ?string} lines of the misused objects
     *                                        and the variable assigned, null
     *                                        when the statement is not a
     *                                        chain of calls
     */
    private function statement(Stmt $stmt, array $bound): ?array
    {
        $expr = $stmt instanceof Expression ? $stmt->expr : null;
        $target = null;

        if ($expr instanceof Assign) {
            $target = $expr->var;
            $expr = $expr->expr;
        }

        $args = $expr instanceof MethodCall
            ? $this->chainArguments($expr, $bound)
            : null;

        // Only a plain local variable: not $$name, nor a property.
        $local = $target instanceof Variable && \is_string($target->name)
            ? $target->name
            : null;

        if (
            $args === null
            || $target instanceof Expr && $local === null
        ) {
            return null;
        }

        return [$args, $local];
    }

    /**
     * @param list<string> $bound
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
     * @param list<string> $bound
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
