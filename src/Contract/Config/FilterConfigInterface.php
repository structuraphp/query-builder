<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use StructuraPhp\QueryBuilder\Enum\Type;

interface FilterConfigInterface
{
    public function column(string $column): static;

    public function type(Type $type): static;

    /**
     * @param list<mixed> $rules
     */
    public function rules(array $rules): static;

    /**
     * @param array<string, string> $messages
     */
    public function messages(array $messages): static;

    public function attribute(string $attribute): static;

    /**
     * Adds the `null` mode: `IS NULL`, or `IS NOT NULL` with `not`.
     */
    public function nullable(): static;

    /**
     * Most values the client may send in `in` mode (256 by default).
     */
    public function maxItems(int $max): static;
}
