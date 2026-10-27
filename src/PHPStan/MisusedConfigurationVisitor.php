<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects the lines where a configuration object is used outside a call
 * chain, or where parent is called. A closure parameter named like a
 * configuration object shadows it.
 */
final class MisusedConfigurationVisitor extends NodeVisitorAbstract
{
    /** @var list<int> */
    public array $lines = [];

    /** @var array<int, string> */
    private array $bound;

    /** @var list<array<int, string>> */
    private array $outer = [];

    /**
     * @param list<string> $bound
     */
    public function __construct(array $bound)
    {
        $this->bound = $bound;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $own = array_map(
                static fn (Param $param): mixed => $param->var->name ?? null,
                $node->params,
            );
            $this->outer[] = $this->bound;
            $this->bound = array_filter(
                $this->bound,
                static fn (mixed $name): bool => !\in_array($name, $own, true),
            );
        }

        if (
            $node instanceof Variable
            && \in_array($node->name, $this->bound, true)
            || $node instanceof StaticCall
            && $this->isParent($node->class)
        ) {
            $this->lines[] = $node->getStartLine();
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $this->bound = array_pop($this->outer) ?? $this->bound;
        }

        return null;
    }

    private function isParent(Node $class): bool
    {
        return $class instanceof Name
            && $class->toLowerString() === 'parent';
    }
}
