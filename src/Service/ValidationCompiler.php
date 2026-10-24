<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use StructuraPhp\QueryBuilder\Enum\Mode;

final readonly class ValidationCompiler
{
    public function __construct(
        private Definition $definition,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            ...$this->scopeRules(),
            ...$this->sortRules(),
            ...$this->relationRules(),
            ...$this->paginationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->definition->filters as $name => $config) {
            foreach ($config->messages as $rule => $message) {
                $messages["{$name}.value.{$rule}"] = $message;
            }
        }

        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach ($arguments as $argument) {
                $key = "{$name}.{$argument->name}";
                foreach ($argument->messages as $rule => $message) {
                    $messages["{$key}.{$rule}"] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->definition->filters as $name => $config) {
            foreach (['value', 'mode', 'not'] as $key) {
                $attributes["{$name}.{$key}"] = $config->attribute ?? $name;
            }
        }

        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach (QueryApplier::fromRequest($arguments) as $argument) {
                $key = "{$name}.{$argument->name}";
                $attributes[$key] = $argument->attribute ?? $key;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function scopeRules(): array
    {
        $rules = [];
        foreach ($this->definition->scopes as $name => [, $arguments]) {
            $arguments = QueryApplier::fromRequest($arguments);
            if ($arguments === []) {
                $rules[$name] = ['sometimes', 'required', 'boolean'];

                continue;
            }

            // `filled` rejects an empty object, which `required_with` would
            // treat as an absent scope.
            $rules[$name] = ['sometimes', 'array', 'filled'];
            foreach ($arguments as $argument) {
                $key = "{$name}.{$argument->name}";
                $presence = $argument->default === null
                    ? ["required_with:{$name}"]
                    : ['sometimes', 'required'];
                $rules[$key] = [...$presence, ...$argument->rules];
                if ($argument->each !== []) {
                    $rules["{$key}.*"] = $argument->each;
                }
            }
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function filterRules(): array
    {
        $rules = [];
        foreach ($this->definition->filters as $name => $config) {
            $type = $config->type;
            if ($type === null) {
                continue;
            }

            $modes = array_map(fn (Mode $mode) => $mode->value, $type->modes());

            $rules[$name] = ['sometimes', 'array'];
            $rules["{$name}.value"] = [
                "required_with:{$name}",
                ...$type->rules(),
                ...$config->rules,
            ];
            $rules["{$name}.mode"] = [
                'sometimes',
                'required',
                Rule::in($modes),
            ];
            $rules["{$name}.not"] = ['sometimes', 'required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function sortRules(): array
    {
        if ($this->definition->sorts === []) {
            return ['sort' => ['prohibited']];
        }

        $names = array_keys($this->definition->sorts);

        // One rule per key rather than `sort.*`, so documentation generators
        // list each sort.
        $rules = ['sort' => ['sometimes', 'array:' . implode(',', $names)]];
        foreach ($names as $name) {
            $rules["sort.{$name}"] = [
                'sometimes',
                'required',
                Rule::in(['asc', 'desc']),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function relationRules(): array
    {
        if ($this->definition->relations === []) {
            return ['with' => ['prohibited']];
        }

        $declared = $this->definition->relations;

        $rule = function (
            string $attribute,
            mixed $value,
            Closure $fail,
        ) use ($declared): void {
            /** @var Closure(string): PotentiallyTranslatedString $fail */
            $with = \is_string($value) ? $value : '';
            $names = QueryApplier::relationNames($with);
            foreach ($names as $name) {
                if (!isset($declared[$name])) {
                    $fail('validation.in')->translate();

                    return;
                }
            }
        };

        return ['with' => ['sometimes', 'string', $rule]];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function paginationRules(): array
    {
        $pagination = $this->definition->pagination;
        // Laravel computes (page - 1) * limit: keep it within an int.
        $maxPage = intdiv(PHP_INT_MAX, $pagination->maxLimit);

        return [
            'pagination' => [
                'sometimes',
                'required',
                Rule::in($pagination->allowed),
            ],
            'page' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:' . $maxPage,
            ],
            'cursor' => ['sometimes', 'required', 'string'],
            'limit' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:' . $pagination->maxLimit,
            ],
        ];
    }
}
