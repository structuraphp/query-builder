<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

/**
 * The arguments a scope takes from the query string, without the query a
 * model scope receives first.
 */
final readonly class ScopeSignature
{
    /**
     * @param list<array{name: string, type: ?string}> $parameters
     */
    public function __construct(
        public array $parameters,
    ) {}
}
