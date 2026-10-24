<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\ValueObject\Config;

use StructuraPhp\QueryBuilder\Contract\Config\FilterConfigInterface;
use StructuraPhp\QueryBuilder\Enum\Type;

final class FilterConfig implements FilterConfigInterface
{
    public private(set) ?string $column = null;

    public private(set) ?Type $type = null;

    /** @var list<mixed> */
    public private(set) array $rules = [];

    /** @var array<string, string> */
    public private(set) array $messages = [];

    public private(set) ?string $attribute = null;

    public function __construct(
        public readonly string $name,
    ) {}

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function type(Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function messages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    public function attribute(string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
