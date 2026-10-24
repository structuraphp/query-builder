<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use BackedEnum;
use Closure;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeConfigInterface;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;

/**
 * @phpstan-type CastType class-string<BackedEnum>|Closure|Type
 */
final class ScopeConfig implements ScopeConfigInterface
{
    public private(set) ?string $method = null;

    /** @var list<string> */
    public private(set) array $context = [];

    /** @var array<string, CastType> */
    public private(set) array $casts = [];

    /** @var array<string, mixed> */
    public private(set) array $defaults = [];

    /** @var array<string, list<mixed>> */
    public private(set) array $rules = [];

    /** @var array<string, list<mixed>> */
    public private(set) array $each = [];

    /** @var array<string, array<string, list<mixed>>> */
    public private(set) array $nested = [];

    /** @var array<string, array<string, string>> */
    public private(set) array $messages = [];

    /** @var array<string, string> */
    public private(set) array $attributes = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function method(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function context(string ...$names): static
    {
        $this->context = [...$this->context, ...array_values($names)];

        return $this;
    }

    public function cast(Closure|string|Type ...$casts): static
    {
        $this->casts = [...$this->casts, ...$this->named($casts)];

        return $this;
    }

    public function default(mixed ...$defaults): static
    {
        $this->defaults = [...$this->defaults, ...$this->named($defaults)];

        return $this;
    }

    public function rules(array ...$rules): static
    {
        $this->rules = [...$this->rules, ...$this->named($rules)];

        return $this;
    }

    public function each(array ...$rules): static
    {
        $this->each = [...$this->each, ...$this->named($rules)];

        return $this;
    }

    public function nested(array ...$rules): static
    {
        $this->nested = [...$this->nested, ...$this->named($rules)];

        return $this;
    }

    public function messages(array ...$messages): static
    {
        $this->messages = [...$this->messages, ...$this->named($messages)];

        return $this;
    }

    public function attribute(string ...$attributes): static
    {
        $this->attributes = [
            ...$this->attributes,
            ...$this->named($attributes),
        ];

        return $this;
    }

    /**
     * @template T
     *
     * @param array<array-key, T> $values
     *
     * @return array<string, T>
     */
    private function named(array $values): array
    {
        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                throw new ConfigurationException(sprintf(
                    'Scope "%s" options must use named arguments.',
                    $this->name,
                ));
            }
        }

        /** @var array<string, T> $values */
        return $values;
    }
}
