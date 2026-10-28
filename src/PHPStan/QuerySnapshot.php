<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

/**
 * What the configuration methods of a query class declare, call by call.
 *
 * An entity is the call that creates it (`filter('x')`, `scope('x')`…) and
 * the calls made on it afterwards, in the order of the code.
 *
 * @phpstan-type CollectedArg array{
 *     kind: 'string'|'int'|'strings'|'case'|'class'|'closure'|'unknown',
 *     value?: string|int|list<string>,
 * }
 * @phpstan-type Call array{
 *     method: string,
 *     file: string,
 *     line: int,
 *     args: array<int|string, CollectedArg>,
 * }
 * @phpstan-type Entity array{call: Call, options: list<Call>}
 */
final readonly class QuerySnapshot
{
    /**
     * @param string $model the class getModel() returns
     * @param list<Entity> $filters
     * @param list<Entity> $scopes
     * @param list<Entity> $sorts
     * @param list<Call> $defaultSorts the calls of sort() but sort(): its
     *                                 default() calls
     * @param list<Entity> $relations
     * @param list<Entity> $counts
     * @param list<Call> $pagination the calls of paginate()
     */
    public function __construct(
        public string $model,
        public array $filters,
        public array $scopes,
        public array $sorts,
        public array $defaultSorts,
        public array $relations,
        public array $counts,
        public array $pagination,
    ) {}

    /**
     * The string an argument holds (a name, a class), given by position or
     * by name; null when it is absent, has no single known value or is not
     * a string.
     *
     * @param Call $call
     */
    public static function string(
        array $call,
        int $position,
        string $name,
    ): ?string {
        $value = self::value($call, $position, $name, ['string', 'class']);

        return \is_string($value) ? $value : null;
    }

    /**
     * @param Call $call
     */
    public static function int(
        array $call,
        int $position,
        string $name,
    ): ?int {
        $value = self::value($call, $position, $name, ['int']);

        return \is_int($value) ? $value : null;
    }

    /**
     * The `Class::Case` of an enum case argument.
     *
     * @param Call $call
     */
    public static function case(
        array $call,
        int $position,
        string $name,
    ): ?string {
        $value = self::value($call, $position, $name, ['case']);

        return \is_string($value) ? $value : null;
    }

    /**
     * The last call to a method.
     *
     * @param list<Call> $calls
     *
     * @return null|Call
     */
    public static function last(array $calls, string $method): ?array
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
     * @param list<string> $kinds
     *
     * @return null|int|list<string>|string
     */
    private static function value(
        array $call,
        int $position,
        string $name,
        array $kinds,
    ): array|int|string|null {
        $arg = $call['args'][$position] ?? $call['args'][$name] ?? [];

        return \in_array($arg['kind'] ?? null, $kinds, true)
            ? $arg['value'] ?? null
            : null;
    }
}
