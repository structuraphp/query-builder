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

    /** @var list<array{string, string}> */
    public private(set) array $defaultSorts = [];

    /** @var array<string, RelationConfig> */
    public private(set) array $relations = [];

    /** @var array<string, string> relations the client may count, by name */
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
                $definition->scopes[$config->name] = [
                    $config,
                    $reflector->arguments($definition->model, $config),
                    $reflector->onBuilder($definition->model, $config),
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

            foreach ($relation->counts as $name) {
                $definition->assertCountable($name);
                if (isset($definition->counts[$name])) {
                    throw new ConfigurationException(\sprintf(
                        'Count "%s" is declared twice.',
                        $name,
                    ));
                }

                $definition->counts[$name] = $name;
            }
        }

        $definition->assertCursorSorts();

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

        $valid = preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name) === 1;

        if (!$valid || !method_exists($this->model, $root)) {
            throw new ConfigurationException(\sprintf(
                'Relation "%s" does not exist on %s.',
                $name,
                $this->model::class,
            ));
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
        if ($this->pagination->default !== 'cursor') {
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

    private function assertValidName(string $name): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
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

        foreach ($sort->defaults as [$name, $direction]) {
            if (!isset($this->sorts[$name])) {
                throw new ConfigurationException(\sprintf(
                    'Default sort "%s" is not declared.',
                    $name,
                ));
            }

            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new ConfigurationException(\sprintf(
                    'Sort direction "%s" must be asc or desc.',
                    $direction,
                ));
            }
        }

        $this->defaultSorts = $sort->defaults;
    }

    private static function assertPagination(Pagination $pagination): void
    {
        foreach ([$pagination->default, ...$pagination->allowed] as $mode) {
            if (!in_array($mode, Pagination::MODES, true)) {
                throw new ConfigurationException(\sprintf(
                    'Unknown pagination mode "%s".',
                    $mode,
                ));
            }
        }

        if (!in_array($pagination->default, $pagination->allowed, true)) {
            throw new ConfigurationException(\sprintf(
                'Default pagination mode "%s" is not allowed.',
                $pagination->default,
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
