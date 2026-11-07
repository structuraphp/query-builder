<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\RouteInfo;
use Override;

/**
 * Documents the scalar form of a filter value next to its list form.
 *
 * A filter value is a scalar, or a list in the `in` mode. Scramble only
 * reads the list rule (`title.value.*`), so it documents `title[value][]`
 * alone: this adds `title[value]`, typed as an item of the list.
 */
final class ScalarFilterValueExtension extends OperationExtension
{
    private const string LIST_SUFFIX = '[value][]';

    #[Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $names = [];
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter) {
                $names[] = $parameter->name;
            }
        }

        $parameters = [];
        foreach ($operation->parameters as $parameter) {
            $scalar = $parameter instanceof Parameter
                ? $this->scalarTwin($parameter, $names)
                : null;
            if ($scalar instanceof Parameter) {
                $parameters[] = $scalar;
            }

            $parameters[] = $parameter;
        }

        $operation->parameters = $parameters;
    }

    /**
     * @param list<string> $names
     */
    private function scalarTwin(Parameter $list, array $names): ?Parameter
    {
        if (!str_ends_with($list->name, self::LIST_SUFFIX)) {
            return null;
        }

        $filter = substr($list->name, 0, -\strlen(self::LIST_SUFFIX));
        $name = $filter . '[value]';
        $schema = $list->schema;
        if (
            !\in_array($filter . '[mode]', $names, true)
            || \in_array($name, $names, true)
            || !$schema instanceof Schema
            || !$schema->type instanceof ArrayType
        ) {
            return null;
        }

        // Scramble also puts the enum of the items on the list, which no
        // array can match.
        $type = $schema->type;
        $type->enum = [];

        $schema = clone $schema;
        $schema->type = $type->items->clone();

        $scalar = clone $list;
        $scalar->name = $name;
        $scalar->schema = $schema;
        $scalar->example = new MissingValue();
        $scalar->examples = [];

        return $scalar;
    }
}
