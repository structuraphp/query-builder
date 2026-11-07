<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use Closure;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;

final class Pagination implements PaginationInterface
{
    public private(set) PaginationMode $default = PaginationMode::Offset;

    public private(set) int $limit = 50;

    public private(set) int $maxLimit = 100;

    /** @var list<PaginationMode> */
    public private(set) array $allowed = [
        PaginationMode::Offset,
        PaginationMode::Cursor,
    ];

    /** @var array<string, array<string, Closure(): string|string>> */
    public private(set) array $messages = [];

    /** @var array<string, Closure(): string|string> by parameter */
    public private(set) array $attributes = [];

    public function default(PaginationMode $mode): static
    {
        $this->default = $mode;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function maxLimit(int $max): static
    {
        $this->maxLimit = $max;

        return $this;
    }

    public function allow(PaginationMode ...$modes): static
    {
        $this->allowed = array_values($modes);

        return $this;
    }

    public function messages(
        array $pagination = [],
        array $limit = [],
        array $page = [],
        array $cursor = [],
    ): static {
        $given = [
            'pagination' => $pagination,
            'limit' => $limit,
            'page' => $page,
            'cursor' => $cursor,
        ];

        foreach ($given as $parameter => $messages) {
            $this->messages[$parameter] = [
                ...$this->messages[$parameter] ?? [],
                ...$messages,
            ];
        }

        return $this;
    }

    public function attribute(
        Closure|string|null $pagination = null,
        Closure|string|null $limit = null,
        Closure|string|null $page = null,
        Closure|string|null $cursor = null,
    ): static {
        $given = [
            'pagination' => $pagination,
            'limit' => $limit,
            'page' => $page,
            'cursor' => $cursor,
        ];

        $this->attributes = [
            ...$this->attributes,
            ...array_filter($given, static fn (mixed $a): bool => $a !== null),
        ];

        return $this;
    }
}
