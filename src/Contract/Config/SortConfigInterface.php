<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use Closure;

interface SortConfigInterface
{
    /**
     * @param literal-string $column
     */
    public function column(string $column): static;

    /**
     * Sorts on the number of related models instead of a column.
     *
     * @param literal-string $relation
     */
    public function count(string $relation): static;

    /**
     * Replaces the message of a rule (`in`, `required`), by rule name. A
     * closure runs only if the error is written.
     *
     * @param array<string, Closure(): string|string> $messages
     */
    public function messages(array $messages): static;

    /**
     * Names the sort in the default messages. A closure runs only if an
     * error is written.
     *
     * @param Closure(): string|string $attribute
     */
    public function attribute(Closure|string $attribute): static;
}
