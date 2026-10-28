<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;

/**
 * Definition::assertHiddenSorts(): a cursor holds the values of the sorted
 * columns, so a hidden column cannot be sorted on when cursors are allowed.
 *
 * @phpstan-import-type Call from QuerySnapshot
 */
final class HiddenSortChecker
{
    /**
     * @return list<IdentifierRuleError>
     */
    public static function check(
        QuerySnapshot $query,
        ModelInspector $model,
    ): array {
        if (!self::cursorAllowed($query->pagination)) {
            return [];
        }

        $hidden = $model->hiddenColumns();
        $errors = [];

        foreach ($query->sorts as $sort) {
            $error = self::sort($sort, $hidden);

            if ($error instanceof IdentifierRuleError) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * An allow() whose modes cannot be read is not taken to allow cursors.
     *
     * @param list<Call> $calls
     */
    private static function cursorAllowed(array $calls): bool
    {
        $allow = null;

        foreach ($calls as $call) {
            if ($call['method'] === 'allow') {
                $allow = $call;
            }
        }

        return $allow === null || array_any(
            $allow['args'],
            static fn (array $arg): bool => $arg['kind'] === 'case'
                && ($arg['value'] ?? null) === PaginationMode::class
                    . '::Cursor',
        );
    }

    /**
     * @param array{call: Call, options: list<Call>} $sort
     * @param list<string> $hidden
     */
    private static function sort(
        array $sort,
        array $hidden,
    ): ?IdentifierRuleError {
        $name = QuerySnapshot::string($sort['call'], 0, 'name');
        $column = null;

        foreach ($sort['options'] as $option) {
            // A sort that counts a relation has no column to hide.
            if ($option['method'] === 'count') {
                return null;
            }

            if ($option['method'] === 'column') {
                $column = $option;
            }
        }

        $at = $column ?? $sort['call'];
        $on = $column === null
            ? $name
            : QuerySnapshot::string($column, 0, 'column');

        if ($name === null || !\in_array($on, $hidden, true)) {
            return null;
        }

        return RuleErrorBuilder::message(\sprintf(
            'Sort "%s" orders on the hidden column "%s": cursor pagination'
            . ' would expose its values.',
            $name,
            $on,
        ))
            ->identifier('structuraQueryBuilder.configuration')
            ->file($at['file'])
            ->line($at['line'])
            ->build();
    }
}
