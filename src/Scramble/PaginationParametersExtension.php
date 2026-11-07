<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Override;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\ValueObject\Config\Pagination;

/**
 * Documents the pagination the query declares.
 *
 * Scramble reads the rules of `pagination`, `limit`, `page` and `cursor`,
 * not the defaults the query configures, nor that `page` only means
 * something to the offset pagination and `cursor` to the cursor one. A
 * parameter no allowed mode reads is not part of the API.
 */
final class PaginationParametersExtension extends OperationExtension
{
    #[Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $definition = QueryDefinition::of($routeInfo);

        if (!$definition instanceof Definition) {
            return;
        }

        $documented = $this->documented($definition->pagination);

        $parameters = [];

        foreach ($operation->parameters as $parameter) {
            $parameters[] = $this->document($parameter, $documented);
        }

        $operation->parameters = array_values(array_filter($parameters));
    }

    /**
     * By name: whether it is kept, its description and its default.
     *
     * @return array<string, array{bool, string, null|int|string}>
     */
    private function documented(Pagination $pagination): array
    {
        $offset = \in_array(PaginationMode::Offset, $pagination->allowed, true);
        $cursor = \in_array(PaginationMode::Cursor, $pagination->allowed, true);

        return [
            'pagination' => [
                true,
                'How the results are paginated.',
                $pagination->default->value,
            ],
            'limit' => [
                $offset || $cursor,
                'Number of results per page.',
                $pagination->limit,
            ],
            'page' => [
                $offset,
                'Page to read, in the `offset` pagination.',
                1,
            ],
            'cursor' => [
                $cursor,
                'Cursor of the page to read, in the `cursor` pagination:'
                . ' the one the previous page returned.',
                null,
            ],
        ];
    }

    /**
     * Null when no allowed mode reads the parameter.
     *
     * @param array<string, array{bool, string, null|int|string}> $documented
     */
    private function document(
        Parameter|Reference $parameter,
        array $documented,
    ): Parameter|Reference|null {
        if (
            !$parameter instanceof Parameter
            || !isset($documented[$parameter->name])
        ) {
            return $parameter;
        }

        [$kept, $description, $default] = $documented[$parameter->name];

        if (!$kept) {
            return null;
        }

        $parameter->description($description);

        if ($default !== null && $parameter->schema instanceof Schema) {
            $parameter->schema->type->default($default);
        }

        return $parameter;
    }
}
