<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Scramble;

use Dedoc\Scramble\Support\RouteInfo;
use ReflectionNamedType;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\Service\QueryRequest;

/**
 * What the query of the request an action takes declares.
 */
final class QueryDefinition
{
    /**
     * Null when the action takes no QueryRequest.
     */
    public static function of(RouteInfo $routeInfo): ?Definition
    {
        $parameters = $routeInfo->reflectionMethod()?->getParameters() ?? [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && is_a($type->getName(), QueryRequest::class, true)
            ) {
                return new ($type->getName())()->definition();
            }
        }

        return null;
    }
}
