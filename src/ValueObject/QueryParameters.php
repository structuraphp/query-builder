<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject;

use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\Service\QueryApplier;
use StructuraPhp\QueryBuilder\Service\QueryPaginator;
use StructuraPhp\QueryBuilder\Service\ValidationCompiler;

/**
 * Validated query parameters, detached from the HTTP request so a domain
 * service can build and paginate the query.
 */
final readonly class QueryParameters
{
    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $input raw data, only used to detect
     *                                    requested scopes
     */
    public function __construct(
        private Definition $definition,
        private array $validated,
        private array $input = [],
    ) {}

    /**
     * Validates raw input outside of an HTTP request (console, job, test),
     * with the same rules, messages and attributes as a QueryRequest.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public static function fromArray(
        FromModel $query,
        array $input,
        ?ValidationFactory $validator = null,
    ): self {
        $definition = Definition::fromQuery($query);
        $compiler = new ValidationCompiler($definition);
        $validator ??= Container::getInstance()->make(ValidationFactory::class);

        /** @var array<string, mixed> $validated */
        $validated = $validator->make(
            $input,
            $compiler->rules(),
            $compiler->messages(),
            $compiler->attributes(),
        )->validate();

        return new self($definition, $validated, $input);
    }

    /**
     * @return Builder<Model>
     */
    public function builder(): Builder
    {
        return new QueryApplier($this->definition)
            ->apply($this->validated, $this->input);
    }

    /**
     * @param Builder<Model> $query
     *
     * @return (
     *     Collection<int, Model>
     *     |CursorPaginator<int, Model>
     *     |LengthAwarePaginator<int, Model>
     * )
     */
    public function paginate(
        Builder $query,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        return new QueryPaginator()
            ->paginate($this->definition, $query, $this->validated);
    }
}
