<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\ValueObject\Config\Pagination;

/**
 * The checks Definition::fromQuery() makes at runtime, but on the calls of
 * the configuration methods. Each entity gets the first error the library
 * would raise for it; a value that cannot be read skips what depends on it.
 *
 * @phpstan-import-type Call from QuerySnapshot
 * @phpstan-import-type Entity from QuerySnapshot
 */
final class ConfigurationChecker
{
    private const string MODE = PaginationMode::class . '::';

    /**
     * @return list<IdentifierRuleError>
     */
    public static function check(
        QuerySnapshot $query,
        ModelInspector $model,
    ): array {
        $errors = [];
        $names = [];

        foreach ($query->filters as $filter) {
            self::push($errors, self::rootName($filter, $names)
                ?? self::filter($filter));
        }

        foreach ($query->scopes as $scope) {
            self::push($errors, self::rootName($scope, $names));
        }

        $sorts = [];

        foreach ($query->sorts as $sort) {
            self::push($errors, self::sort($sort, $sorts, $model));
        }

        self::push($errors, self::pagination($query->pagination));

        foreach ($query->defaultSorts as $default) {
            self::push($errors, self::defaultSort($default, $query));
        }

        $relations = [];

        foreach ($query->relations as $relation) {
            self::push($errors, self::relation($relation, $relations, $model));
        }

        foreach ($query->relations as $index => $relation) {
            $error = self::parent($relation, $index, $query, $model);
            self::push($errors, $error);
        }

        $counts = [];

        foreach ($query->counts as $count) {
            self::push($errors, self::count($count, $counts, $model));
        }

        return [
            ...$errors,
            ...ScopeArgumentChecker::check($query, $model),
            ...HiddenSortChecker::check($query, $model),
        ];
    }

    /**
     * @param list<IdentifierRuleError> $errors
     */
    private static function push(
        array &$errors,
        ?IdentifierRuleError $error,
    ): void {
        if ($error instanceof IdentifierRuleError) {
            $errors[] = $error;
        }
    }

    /**
     * Filters and scopes share the root of the query string.
     *
     * @param Entity $entity
     * @param list<?string> $names
     */
    private static function rootName(
        array $entity,
        array &$names,
    ): ?IdentifierRuleError {
        $call = $entity['call'];
        $name = QuerySnapshot::string($call, 0, 'name');

        if ($name === null) {
            return null;
        }

        $twice = \in_array($name, $names, true);
        $names[] = $name;

        if (preg_match(Definition::NAME_PATTERN, $name) !== 1) {
            return self::error($call, \sprintf(
                '"%s" is not a valid name.',
                $name,
            ));
        }

        if (\in_array($name, Definition::RESERVED, true)) {
            return self::error($call, \sprintf(
                '"%s" is a reserved query parameter.',
                $name,
            ));
        }

        return $twice
            ? self::error($call, \sprintf('"%s" is declared twice.', $name))
            : null;
    }

    /**
     * The options of a filter whose name passed the checks on names.
     *
     * @param Entity $filter
     */
    private static function filter(array $filter): ?IdentifierRuleError
    {
        $name = QuerySnapshot::string($filter['call'], 0, 'name');

        if ($name === null) {
            return null;
        }

        if (self::last($filter['options'], 'type') === null) {
            return self::error($filter['call'], \sprintf(
                'Filter "%s" has no type.',
                $name,
            ));
        }

        $items = self::last($filter['options'], 'maxitems');
        if ($items !== null && self::below($items, 'max')) {
            return self::error($items, \sprintf(
                'Filter "%s" must accept at least one item in in mode.',
                $name,
            ));
        }

        $length = self::last($filter['options'], 'maxlength');
        if ($length !== null && self::below($length, 'max')) {
            return self::error($length, \sprintf(
                'Filter "%s" must accept at least one character.',
                $name,
            ));
        }

        return null;
    }

    /**
     * Whether the integer a call holds is known and under one.
     *
     * @param Call $call
     */
    private static function below(array $call, string $name): bool
    {
        $value = QuerySnapshot::int($call, 0, $name);

        return $value !== null && $value < 1;
    }

    /**
     * @param Entity $sort
     * @param list<?string> $names
     */
    private static function sort(
        array $sort,
        array &$names,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $name = QuerySnapshot::string($sort['call'], 0, 'name');

        if ($name === null) {
            return null;
        }

        $twice = \in_array($name, $names, true);
        $names[] = $name;

        if (preg_match(Definition::NAME_PATTERN, $name) !== 1) {
            return self::error($sort['call'], \sprintf(
                '"%s" is not a valid name.',
                $name,
            ));
        }

        if ($twice) {
            return self::error($sort['call'], \sprintf(
                'Sort "%s" is declared twice.',
                $name,
            ));
        }

        $count = self::last($sort['options'], 'count');

        if ($count === null) {
            return null;
        }

        if (self::last($sort['options'], 'column') !== null) {
            return self::error($count, \sprintf(
                'Sort "%s" cannot have both column() and count().',
                $name,
            ));
        }

        return self::countable($count, 'relation', $model);
    }

    /**
     * @param Call $call
     */
    private static function countable(
        array $call,
        string $argument,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $relation = QuerySnapshot::string($call, 0, $argument);

        if ($relation === null) {
            return null;
        }

        if (preg_match(Definition::NAME_PATTERN, $relation) !== 1) {
            return self::error($call, \sprintf(
                '"%s" is not a valid name.',
                $relation,
            ));
        }

        return $model->hasRelation($relation)
            ? null
            : self::missingRelation($call, $relation, $model);
    }

    /**
     * @param Call $call
     */
    private static function missingRelation(
        array $call,
        string $name,
        ModelInspector $model,
    ): IdentifierRuleError {
        return self::error($call, \sprintf(
            'Relation "%s" does not exist on %s.',
            $name,
            $model->name(),
        ));
    }

    /**
     * @param Call $default
     */
    private static function defaultSort(
        array $default,
        QuerySnapshot $query,
    ): ?IdentifierRuleError {
        $name = QuerySnapshot::string($default, 0, 'name');

        if ($name === null) {
            return null;
        }

        $declared = null;

        foreach ($query->sorts as $sort) {
            $other = QuerySnapshot::string($sort['call'], 0, 'name');

            // A sort without a readable name may be this one.
            if ($other === null) {
                return null;
            }

            if ($other === $name && $declared === null) {
                $declared = $sort;
            }
        }

        if ($declared === null) {
            return self::error($default, \sprintf(
                'Default sort "%s" is not declared.',
                $name,
            ));
        }

        if (
            self::last($declared['options'], 'count') !== null
            && self::defaultMode($query->pagination) === self::MODE . 'Cursor'
        ) {
            return self::error($default, \sprintf(
                'Default sort "%s" counts a relation: it cannot be used'
                . ' with the default cursor pagination.',
                $name,
            ));
        }

        return null;
    }

    /**
     * @param Entity $relation
     * @param list<?string> $names
     */
    private static function relation(
        array $relation,
        array &$names,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $name = QuerySnapshot::string($relation['call'], 0, 'name');

        if ($name === null) {
            return null;
        }

        $twice = \in_array($name, $names, true);
        $names[] = $name;

        if (
            preg_match(Definition::RELATION_PATTERN, $name) !== 1
            || !$model->hasRelation(explode('.', $name)[0])
        ) {
            return self::missingRelation($relation['call'], $name, $model);
        }

        return $twice
            ? self::error($relation['call'], \sprintf(
                'Relation "%s" is declared twice.',
                $name,
            ))
            : null;
    }

    /**
     * Laravel loads every parent of a nested relation. A relation that
     * failed an earlier check is not looked at again.
     *
     * @param Entity $relation
     */
    private static function parent(
        array $relation,
        int $index,
        QuerySnapshot $query,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $name = QuerySnapshot::string($relation['call'], 0, 'name');

        if (
            $name === null
            || preg_match(Definition::RELATION_PATTERN, $name) !== 1
            || !$model->hasRelation(explode('.', $name)[0])
        ) {
            return null;
        }

        $dot = strrpos($name, '.');

        if ($dot === false) {
            return null;
        }

        $parent = substr($name, 0, $dot);
        $declared = [];

        foreach ($query->relations as $position => $other) {
            $other = QuerySnapshot::string($other['call'], 0, 'name');

            // A relation without a readable name may be the parent.
            if ($other === null) {
                return null;
            }

            if ($other === $name && $position < $index) {
                return null;
            }

            $declared[] = $other;
        }

        return \in_array($parent, $declared, true)
            ? null
            : self::error($relation['call'], \sprintf(
                'Relation "%s" needs its parent "%s" declared.',
                $name,
                $parent,
            ));
    }

    /**
     * @param Call $count
     * @param list<?string> $names
     */
    private static function count(
        array $count,
        array &$names,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $name = QuerySnapshot::string($count, 0, 'name');
        $twice = $name !== null && \in_array($name, $names, true);
        $names[] = $name;

        return self::countable($count, 'name', $model)
            ?? ($twice
                ? self::error($count, \sprintf(
                    'Count "%s" is declared twice.',
                    $name,
                ))
                : null);
    }

    /**
     * @param list<Call> $calls
     */
    private static function pagination(array $calls): ?IdentifierRuleError
    {
        $default = self::last($calls, 'default');
        $allow = self::last($calls, 'allow');
        $at = $default ?? $allow;

        $mode = self::defaultMode($calls);
        $allowed = $allow === null
            ? array_map(
                static fn (PaginationMode $mode): string => self::MODE
                    . $mode->name,
                new Pagination()->allowed,
            )
            : self::modes($allow);

        // Without a call, the default mode is allowed.
        if (
            $at === null
            || $mode === null
            || $allowed === null
            || \in_array($mode, $allowed, true)
        ) {
            return self::limit($calls);
        }

        return self::error($at, \sprintf(
            'Default pagination mode "%s" is not allowed.',
            strtolower(substr($mode, \strlen(self::MODE))),
        ));
    }

    /**
     * @param list<Call> $calls
     */
    private static function limit(array $calls): ?IdentifierRuleError
    {
        $limit = self::last($calls, 'limit');
        $max = self::last($calls, 'maxlimit');
        $at = $limit ?? $max;

        $value = $limit === null
            ? new Pagination()->limit
            : QuerySnapshot::int($limit, 0, 'limit');
        $ceiling = $max === null
            ? new Pagination()->maxLimit
            : QuerySnapshot::int($max, 0, 'max');

        if ($at === null || $value === null || $ceiling === null) {
            return null;
        }

        return $value < 1 || $value > $ceiling
            ? self::error($at, \sprintf(
                'Pagination limit must be between 1 and %d.',
                $ceiling,
            ))
            : null;
    }

    /**
     * The `Class::Case` of the default mode; null when it cannot be read.
     *
     * @param list<Call> $calls
     */
    private static function defaultMode(array $calls): ?string
    {
        $default = self::last($calls, 'default');

        return $default === null
            ? self::MODE . new Pagination()->default->name
            : self::mode($default, 0, 'mode');
    }

    /**
     * The `Class::Case` of every mode of allow(); null when one cannot be
     * read.
     *
     * @param Call $allow
     *
     * @return null|list<string>
     */
    private static function modes(array $allow): ?array
    {
        $modes = [];
        $counter = \count($allow['args']);

        for ($i = 0; $i < $counter; $i++) {
            $mode = self::mode($allow, $i, '');

            if ($mode === null) {
                return null;
            }

            $modes[] = $mode;
        }

        return $modes;
    }

    /**
     * @param Call $call
     */
    private static function mode(
        array $call,
        int $position,
        string $name,
    ): ?string {
        $case = QuerySnapshot::case($call, $position, $name);

        return $case !== null && str_starts_with($case, self::MODE)
            ? $case
            : null;
    }

    /**
     * @param list<Call> $calls
     *
     * @return null|Call
     */
    private static function last(array $calls, string $method): ?array
    {
        $found = null;

        foreach ($calls as $call) {
            if ($call['method'] === $method) {
                $found = $call;
            }
        }

        return $found;
    }

    /**
     * @param Call $call
     */
    private static function error(
        array $call,
        string $message,
    ): IdentifierRuleError {
        return RuleErrorBuilder::message($message)
            ->identifier('structuraQueryBuilder.configuration')
            ->file($call['file'])
            ->line($call['line'])
            ->build();
    }
}
