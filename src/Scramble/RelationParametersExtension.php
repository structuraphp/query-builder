<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Override;
use StructuraPhp\QueryBuilder\Service\Definition;

/**
 * Documents which relations `with` and `count` accept.
 *
 * They are comma separated lists, checked by a closure Scramble cannot read:
 * it documents a bare string. The names come from the query of the request
 * the action takes.
 */
final class RelationParametersExtension extends OperationExtension
{
    #[Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $definition = QueryDefinition::of($routeInfo);

        if (!$definition instanceof Definition) {
            return;
        }

        $this->document(
            $operation,
            'with',
            'Relations to load',
            array_keys($definition->relations),
        );
        $this->document(
            $operation,
            'count',
            'Relations to count',
            array_keys($definition->counts),
        );
    }

    /**
     * A query that declares none prohibits the parameter: it is not part of
     * the API.
     *
     * @param list<string> $names
     */
    private function document(
        Operation $operation,
        string $name,
        string $label,
        array $names,
    ): void {
        $parameters = [];

        foreach ($operation->parameters as $parameter) {
            if (
                !$parameter instanceof Parameter
                || $parameter->name !== $name
            ) {
                $parameters[] = $parameter;

                continue;
            }

            if ($names === []) {
                continue;
            }

            $list = implode('|', array_map(preg_quote(...), $names));
            $schema = $parameter->schema;

            if ($schema instanceof Schema) {
                $schema->type->pattern(
                    '^\s*(' . $list . ')\s*(,\s*(' . $list . ')\s*)*$',
                );
            }

            $parameter->description(\sprintf(
                '%s, separated by commas: %s.',
                $label,
                implode(', ', array_map(
                    static fn (string $item): string => "`{$item}`",
                    $names,
                )),
            ));
            $parameters[] = $parameter;
        }

        $operation->parameters = $parameters;
    }
}
