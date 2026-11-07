<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\RouteInfo;
use Override;

/**
 * Drops the bounds no JSON number holds exactly.
 *
 * A float is bounded by the largest float (its rule has to be readable by
 * Scramble), and `page` by the largest integer PHP computes an offset with.
 * Those bounds only guard the server: a client generated from the document
 * rounds them, or refuses them.
 */
final class NumberBoundsExtension extends OperationExtension
{
    /** The largest integer a JSON number holds exactly (2^53 - 1). */
    private const int SAFE = 9007199254740991;

    #[Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->parameters as $parameter) {
            if (
                !$parameter instanceof Parameter
                || !$parameter->schema instanceof Schema
            ) {
                continue;
            }

            $type = $parameter->schema->type;

            if ($type instanceof ArrayType) {
                $type = $type->items;
            }

            if ($type instanceof NumberType) {
                $type->min = $this->unsafe($type->min) ? null : $type->min;
                $type->max = $this->unsafe($type->max) ? null : $type->max;
            }
        }
    }

    private function unsafe(mixed $bound): bool
    {
        return (\is_int($bound) || \is_float($bound))
            && abs($bound) > self::SAFE;
    }
}
