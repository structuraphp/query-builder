<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject;

use Closure;

final readonly class ScopeArgument
{
    /**
     * @param list<mixed> $rules type rule then custom rules, without the
     *                           presence rules
     * @param list<mixed> $each
     * @param array<string, string> $messages
     * @param Closure(mixed): mixed $cast
     * @param null|(Closure(): mixed) $default null when required
     */
    public function __construct(
        public string $name,
        public array $rules,
        public array $each,
        public array $messages,
        public ?string $attribute,
        public Closure $cast,
        public ?Closure $default,
    ) {}
}
