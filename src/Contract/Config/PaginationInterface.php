<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use Closure;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;

interface PaginationInterface
{
    public function default(PaginationMode $mode): static;

    public function limit(int $limit): static;

    public function maxLimit(int $max): static;

    public function allow(PaginationMode ...$modes): static;

    /**
     * Replaces the message of a rule of a parameter, by rule name (`max` and
     * `min` for `limit`, `in` for `pagination`...). A closure runs only if
     * the error is written.
     *
     * @param array<string, Closure(): string|string> $pagination
     * @param array<string, Closure(): string|string> $limit
     * @param array<string, Closure(): string|string> $page
     * @param array<string, Closure(): string|string> $cursor
     */
    public function messages(
        array $pagination = [],
        array $limit = [],
        array $page = [],
        array $cursor = [],
    ): static;

    /**
     * Names a parameter in the default messages. A closure runs only if an
     * error is written.
     *
     * @param null|(Closure(): string)|string $pagination
     * @param null|(Closure(): string)|string $limit
     * @param null|(Closure(): string)|string $page
     * @param null|(Closure(): string)|string $cursor
     */
    public function attribute(
        Closure|string|null $pagination = null,
        Closure|string|null $limit = null,
        Closure|string|null $page = null,
        Closure|string|null $cursor = null,
    ): static;
}
