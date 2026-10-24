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
     * @param bool $context given by the code, never read from the request
     * @param bool $lazy a closure given as context is called, with the
     *                   request arguments it names
     * @param array<string, list<mixed>> $nested rules by dot path inside the
     *                                           argument
     */
    public function __construct(
        public string $name,
        public array $rules,
        public array $each,
        public array $messages,
        public ?string $attribute,
        public Closure $cast,
        public ?Closure $default,
        public bool $context = false,
        public bool $lazy = false,
        public array $nested = [],
    ) {}
}
