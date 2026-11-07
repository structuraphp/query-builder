<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\ValueObject\Config\Pagination;

/**
 * Definition::assertPagination(), on the calls of paginate().
 *
 * @phpstan-import-type Call from QuerySnapshot
 */
final class PaginationChecker
{
    private const string MODE = PaginationMode::class . '::';

    /**
     * @param list<Call> $calls
     */
    public static function check(array $calls): ?IdentifierRuleError
    {
        $default = QuerySnapshot::last($calls, 'default');
        $allow = QuerySnapshot::last($calls, 'allow');
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
     * Whether the default mode is known to be cursor.
     *
     * @param list<Call> $calls
     */
    public static function cursorByDefault(array $calls): bool
    {
        return self::defaultMode($calls) === self::MODE . 'Cursor';
    }

    /**
     * @param list<Call> $calls
     */
    private static function limit(array $calls): ?IdentifierRuleError
    {
        $limit = QuerySnapshot::last($calls, 'limit');
        $max = QuerySnapshot::last($calls, 'maxlimit');
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
        $default = QuerySnapshot::last($calls, 'default');

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
