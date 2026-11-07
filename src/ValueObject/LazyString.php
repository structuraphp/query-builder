<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject;

use Closure;
use Override;
use Stringable;

/**
 * A message or an attribute that is computed when Laravel writes the error,
 * not when the query is configured: a translation only runs for a request
 * that fails.
 */
final class LazyString implements Stringable
{
    private ?string $value = null;

    /**
     * @param Closure(): string $resolve
     */
    public function __construct(
        private readonly Closure $resolve,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return $this->value ??= ($this->resolve)();
    }

    /**
     * @param Closure(): string|string $value
     */
    public static function of(Closure|string $value): self|string
    {
        return $value instanceof Closure ? new self($value) : $value;
    }
}
