<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Illuminate\Database\Eloquent\Model;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\ValueObject\Config\Filter;
use StructuraPhp\QueryBuilder\ValueObject\Config\FilterConfig;
use StructuraPhp\QueryBuilder\ValueObject\Config\Pagination;
use StructuraPhp\QueryBuilder\ValueObject\Config\Relation;
use StructuraPhp\QueryBuilder\ValueObject\Config\RelationConfig;
use StructuraPhp\QueryBuilder\ValueObject\Config\Scope;
use StructuraPhp\QueryBuilder\ValueObject\Config\ScopeConfig;
use StructuraPhp\QueryBuilder\ValueObject\Config\Sort;
use StructuraPhp\QueryBuilder\ValueObject\Config\SortConfig;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;

final class Definition
{
    public const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public const string RELATION_PATTERN = '/^[A-Za-z_][A-Za-z0-9_.]*$/';

    public const array RESERVED = [
        'with',
        'sort',
        'pagination',
        'page',
        'cursor',
        'limit',
        'count',
    ];

    /** @var array<string, FilterConfig> */
    public private(set) array $filters = [];

    /**
     * The flag tells a method of the model's builder from a model scope.
     *
     * @var array<string, array{ScopeConfig, list<ScopeArgument>, bool}>
     */
    public private(set) array $scopes = [];

    /** @var array<string, SortConfig> */
    public private(set) array $sorts = [];

    /** @var list<array{string, Direction}> */
    public private(set) array $defaultSorts = [];

    /** @var array<string, RelationConfig> */
    public private(set) array $relations = [];

    /** @var list<string> relations the client may count */
    public private(set) array $counts = [];

    private function __construct(
        public readonly Model $model,
        public readonly Pagination $pagination,
    ) {}

    public static function fromQuery(FromModel $query): self
    {
        $model = $query->getModel();

        $pagination = new Pagination();
        if ($query instanceof WithPaginate) {
            $query->paginate($pagination);
        }

        self::assertPagination($pagination);

        $definition = new self(new $model(), $pagination);

        if ($query instanceof WithFilter) {
            $filter = new Filter();
            $query->filter($filter);
            foreach ($filter->filters as $config) {
                $definition->addFilter($config);
            }
        }

        if ($query instanceof WithScope) {
            $scope = new Scope();
            $query->scope($scope);
            $reflector = new ScopeReflector();
            foreach ($scope->scopes as $config) {
                $definition->assertName($config->name);
                [$arguments, $onBuilder] = $reflector->reflect(
                    $definition->model,
                    $config,
                );
                $definition->scopes[$config->name] = [
                    $config,
                    $arguments,
                    $onBuilder,
                ];
            }
        }

        if ($query instanceof WithSort) {
            $sort = new Sort();
            $query->sort($sort);
            $definition->addSort($sort);
        }

        if ($query instanceof WithRelation) {
            $relation = new Relation();
            $query->relation($relation);
            foreach ($relation->relations as $config) {
                $definition->assertRelation($config->name);
                if (isset($definition->relations[$config->name])) {
                    throw new ConfigurationException(\sprintf(
                        'Relation "%s" is declared twice.',
                        $config->name,
                    ));
                }

                $definition->relations[$config->name] = $config;
            }

            $definition->assertParents();

            foreach ($relation->counts as $name) {
                $definition->assertCountable($name);
                if (\in_array($name, $definition->counts, true)) {
                    throw new ConfigurationException(\sprintf(
                        'Count "%s" is declared twice.',
                        $name,
                    ));
                }

                $definition->counts[] = $name;
            }
        }

        $definition->assertCursorSorts();
        $definition->assertHiddenSorts();

        return $definition;
    }

    /**
     * Filters and scopes share the root of the query string.
     */
    private function assertName(string $name): void
    {
        $this->assertValidName($name);

        if (in_array($name, self::RESERVED, true)) {
            throw new ConfigurationException(\sprintf(
                '"%s" is a reserved query parameter.',
                $name,
            ));
        }

        if (isset($this->filters[$name]) || isset($this->scopes[$name])) {
            throw new ConfigurationException(\sprintf(
                '"%s" is declared twice.',
                $name,
            ));
        }
    }

    /**
     * Only the root relation is checked: nested ones would need the related
     * models.
     */
    private function assertRelation(string $name): void
    {
        $root = explode('.', $name)[0];

        $valid = preg_match(self::RELATION_PATTERN, $name) === 1;

        if (!$valid || !method_exists($this->model, $root)) {
            throw new ConfigurationException(\sprintf(
                'Relation "%s" does not exist on %s.',
                $name,
                $this->model::class,
            ));
        }
    }

    /**
     * Laravel loads every parent of a nested relation: each one must be
     * declared, so its fields() apply.
     */
    private function assertParents(): void
    {
        foreach (array_keys($this->relations) as $name) {
            $dot = strrpos($name, '.');
            if ($dot === false) {
                continue;
            }

            $parent = substr($name, 0, $dot);
            if (!isset($this->relations[$parent])) {
                throw new ConfigurationException(\sprintf(
                    'Relation "%s" needs its parent "%s" declared.',
                    $name,
                    $parent,
                ));
            }
        }
    }

    /**
     * A count is a root relation of the model: `withCount` aliases a nested
     * one ambiguously.
     */
    private function assertCountable(string $name): void
    {
        $this->assertValidName($name);

        if (!method_exists($this->model, $name)) {
            throw new ConfigurationException(\sprintf(
                'Relation "%s" does not exist on %s.',
                $name,
                $this->model::class,
            ));
        }
    }

    /**
     * Cursor pagination cannot compare a computed count in its WHERE clause.
     */
    private function assertCursorSorts(): void
    {
        if ($this->pagination->default !== PaginationMode::Cursor) {
            return;
        }

        foreach ($this->defaultSorts as [$name]) {
            if ($this->sorts[$name]->count !== null) {
                throw new ConfigurationException(\sprintf(
                    'Default sort "%s" counts a relation: it cannot be used'
                    . ' with the default cursor pagination.',
                    $name,
                ));
            }
        }
    }

    /**
     * A cursor holds the values of the sorted columns, and the client reads
     * it: a hidden column would leak.
     */
    private function assertHiddenSorts(): void
    {
        $cursor = \in_array(
            PaginationMode::Cursor,
            $this->pagination->allowed,
            true,
        );
        if (!$cursor) {
            return;
        }

        $hidden = $this->model->getHidden();
        foreach ($this->sorts as $name => $config) {
            $column = $config->column ?? $name;
            if ($config->count === null && \in_array($column, $hidden, true)) {
                throw new ConfigurationException(\sprintf(
                    'Sort "%s" orders on the hidden column "%s": cursor'
                    . ' pagination would expose its values.',
                    $name,
                    $column,
                ));
            }
        }
    }

    private function assertValidName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new ConfigurationException(\sprintf(
                '"%s" is not a valid name.',
                $name,
            ));
        }
    }

    private function addFilter(FilterConfig $config): void
    {
        $this->assertName($config->name);

        if (!$config->type instanceof Type) {
            throw new ConfigurationException(\sprintf(
                'Filter "%s" has no type.',
                $config->name,
            ));
        }

        if ($config->maxItems < 1) {
            throw new ConfigurationException(\sprintf(
                'Filter "%s" must accept at least one item in in mode.',
                $config->name,
            ));
        }

        if ($config->maxLength < 1) {
            throw new ConfigurationException(\sprintf(
                'Filter "%s" must accept at least one character.',
                $config->name,
            ));
        }

        $this->filters[$config->name] = $config;
    }

    private function addSort(Sort $sort): void
    {
        foreach ($sort->sorts as $config) {
            $this->assertValidName($config->name);

            if (isset($this->sorts[$config->name])) {
                throw new ConfigurationException(\sprintf(
                    'Sort "%s" is declared twice.',
                    $config->name,
                ));
            }

            if ($config->count !== null) {
                if ($config->column !== null) {
                    throw new ConfigurationException(\sprintf(
                        'Sort "%s" cannot have both column() and count().',
                        $config->name,
                    ));
                }

                $this->assertCountable($config->count);
            }

            $this->sorts[$config->name] = $config;
        }

        foreach ($sort->defaults as [$name]) {
            if (!isset($this->sorts[$name])) {
                throw new ConfigurationException(\sprintf(
                    'Default sort "%s" is not declared.',
                    $name,
                ));
            }
        }

        $this->defaultSorts = $sort->defaults;
    }

    private static function assertPagination(Pagination $pagination): void
    {
        if (!in_array($pagination->default, $pagination->allowed, true)) {
            throw new ConfigurationException(\sprintf(
                'Default pagination mode "%s" is not allowed.',
                $pagination->default->value,
            ));
        }

        $limit = $pagination->limit;
        if ($limit < 1 || $limit > $pagination->maxLimit) {
            throw new ConfigurationException(\sprintf(
                'Pagination limit must be between 1 and %d.',
                $pagination->maxLimit,
            ));
        }
    }
}
