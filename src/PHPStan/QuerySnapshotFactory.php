<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Reflection\ClassReflection;

/**
 * Rebuilds the configuration of a query class from the collected methods,
 * each one taken from the class that declares it.
 *
 * @phpstan-import-type CollectedCall from ConfigurationCollector
 * @phpstan-import-type CollectedMethod from ConfigurationCollector
 * @phpstan-import-type Call from QuerySnapshot
 * @phpstan-import-type Entity from QuerySnapshot
 */
final class QuerySnapshotFactory
{
    /**
     * @param array<string, array<string, CollectedMethod>> $byClassAndMethod
     *
     * @return null|QuerySnapshot null when the model or a configuration
     *                            method cannot be read
     */
    public static function create(
        ClassReflection $query,
        array $byClassAndMethod,
    ): ?QuerySnapshot {
        $model = self::method($query, 'getModel', $byClassAndMethod);

        if (!isset($model['model'])) {
            return null;
        }

        $sections = [];

        foreach (array_keys(ConfigurationMethods::SECTIONS) as $name) {
            $sections[$name] = ['entities' => [], 'calls' => []];

            if (!ConfigurationMethods::isConfiguration($query, $name)) {
                continue;
            }

            $method = self::method($query, $name, $byClassAndMethod);

            if (!isset($method['calls'])) {
                return null;
            }

            $sections[$name] = self::replay($method['file'], $method['calls']);
        }

        return new QuerySnapshot(
            model: $model['model'],
            filters: $sections['filter']['entities'],
            scopes: $sections['scope']['entities'],
            sorts: $sections['sort']['entities'],
            defaultSorts: $sections['sort']['calls'],
            relations: self::entities($sections['relation'], false),
            counts: self::entities($sections['relation'], true),
            pagination: $sections['paginate']['calls'],
        );
    }

    /**
     * The entities of the relation section created by count(), or by
     * relation().
     *
     * @param array{entities: list<Entity>, calls: list<Call>} $section
     *
     * @return list<Entity>
     */
    private static function entities(array $section, bool $counts): array
    {
        return array_values(array_filter(
            $section['entities'],
            static fn (array $entity): bool => ($entity['call']['method']
                === 'count') === $counts,
        ));
    }

    /**
     * @param array<string, array<string, CollectedMethod>> $byClassAndMethod
     *
     * @return null|CollectedMethod
     */
    private static function method(
        ClassReflection $query,
        string $name,
        array $byClassAndMethod,
    ): ?array {
        $declaring = $query->getNativeMethod($name)->getDeclaringClass();

        return $byClassAndMethod[$declaring->getName()][strtolower($name)]
            ?? null;
    }

    /**
     * @param list<CollectedCall> $calls
     *
     * @return array{entities: list<Entity>, calls: list<Call>}
     */
    private static function replay(string $file, array $calls): array
    {
        $created = [];
        $options = [];
        $root = [];

        foreach ($calls as $index => $collected) {
            $call = [
                'method' => $collected['method'],
                'file' => $file,
                'line' => $collected['line'],
                'args' => $collected['args'],
            ];

            if ($collected['result'] === $index + 1) {
                $created[$index + 1] = $call;
            } elseif ($collected['target'] === 0) {
                $root[] = $call;
            } else {
                $options[$collected['target']][] = $call;
            }
        }

        $entities = [];

        foreach ($created as $number => $call) {
            $entities[] = [
                'call' => $call,
                'options' => $options[$number] ?? [],
            ];
        }

        return ['entities' => $entities, 'calls' => $root];
    }
}
