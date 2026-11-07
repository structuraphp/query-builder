<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use StructuraPhp\QueryBuilder\Service\Definition;

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

        self::push($errors, PaginationChecker::check($query->pagination));

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
            self::push($errors, self::count($count['call'], $counts, $model));
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

        if (QuerySnapshot::last($filter['options'], 'type') === null) {
            return self::error($filter['call'], \sprintf(
                'Filter "%s" has no type.',
                $name,
            ));
        }

        $items = QuerySnapshot::last($filter['options'], 'maxitems');
        if ($items !== null && self::below($items, 'max')) {
            return self::error($items, \sprintf(
                'Filter "%s" must accept at least one item in in mode.',
                $name,
            ));
        }

        $length = QuerySnapshot::last($filter['options'], 'maxlength');
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

        $count = QuerySnapshot::last($sort['options'], 'count');

        if ($count === null) {
            return null;
        }

        if (QuerySnapshot::last($sort['options'], 'column') !== null) {
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
        $names = self::names($query->sorts);

        // A sort without a readable name may be this one.
        if ($name === null || \in_array(null, $names, true)) {
            return null;
        }

        $index = array_search($name, $names, true);

        if ($index === false) {
            return self::error($default, \sprintf(
                'Default sort "%s" is not declared.',
                $name,
            ));
        }

        $count = QuerySnapshot::last($query->sorts[$index]['options'], 'count');

        if (
            $count !== null
            && PaginationChecker::cursorByDefault($query->pagination)
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
        $names = self::names($query->relations);

        // A relation without a readable name may be the parent.
        if ($dot === false || \in_array(null, $names, true)) {
            return null;
        }

        if (array_search($name, $names, true) !== $index) {
            return null;
        }

        $parent = substr($name, 0, $dot);

        return \in_array($parent, $names, true)
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
     * The name of each entity, null when it cannot be read.
     *
     * @param list<Entity> $entities
     *
     * @return list<?string>
     */
    private static function names(array $entities): array
    {
        return array_map(
            static fn (array $entity): ?string => QuerySnapshot::string(
                $entity['call'],
                0,
                'name',
            ),
            $entities,
        );
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
