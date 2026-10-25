<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use StructuraPhp\QueryBuilder\Enum\Mode;
use Throwable;

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
     * Runs closure casts once the rules of their argument passed, so a cast
     * only sees valid data. A cast that throws is a validation error.
     */
    public function after(Validator $validator): void
    {
        $errors = $validator->errors();
        $data = $validator->getData();

        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach ($arguments as $argument) {
                $key = "{$name}.{$argument->name}";
                $failed = array_any(
                    $errors->keys(),
                    fn (string $error): bool => $error === $key
                        || str_starts_with($error, "{$key}."),
                );
                if (
                    !$argument->check instanceof Closure
                    || $failed
                    || !Arr::has($data, $key)
                ) {
                    continue;
                }

                try {
                    ($argument->check)(Arr::get($data, $key));
                } catch (Throwable) {
                    $attribute = $validator->getDisplayableAttribute($key);
                    $message = trans('validation.in', [
                        'attribute' => $attribute,
                    ]);
                    $errors->add(
                        $key,
                        \is_string($message) ? $message : 'validation.in',
                    );
                }
            }
        }
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
                $messages["{$name}.value.*.{$rule}"] = $message;
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
            foreach (['value', 'value.*', 'mode', 'not'] as $key) {
                $attributes["{$name}.{$key}"] = $config->attribute ?? $name;
            }
        }

        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach (QueryApplier::fromRequest($arguments) as $argument) {
                $key = "{$name}.{$argument->name}";
                $attributes[$key] = $argument->attribute ?? $key;
                foreach ($argument->nestedAttributes as $path => $attribute) {
                    $attributes["{$key}.{$path}"] = $attribute;
                }
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

                $nested = $argument->nested;
                if ($argument->each !== []) {
                    $nested['*'] = [...$argument->each, ...$nested['*'] ?? []];
                }

                // A nested `required` must not fire when the argument itself
                // is absent.
                foreach ($nested as $path => $pathRules) {
                    $rules["{$key}.{$path}"] = [
                        "exclude_without:{$key}",
                        ...$pathRules,
                    ];
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

            $modes = $type->modes();
            if ($config->nullable) {
                $modes[] = Mode::IsNull;
            }

            $single = [...$type->rules(), ...$config->rules];

            $rules[$name] = ['sometimes', 'array'];
            $rules["{$name}.value"] = $this->valueRules($name, $modes, $single);
            if (\in_array(Mode::In, $modes, true)) {
                // In `in` mode, each item gets the rules of a single value.
                $rules["{$name}.value.*"] = $single;
            }

            $rules["{$name}.mode"] = [
                'sometimes',
                'required',
                Rule::in(array_map(fn (Mode $mode) => $mode->value, $modes)),
            ];
            $rules["{$name}.not"] = ['sometimes', 'required', 'boolean'];
        }

        return $rules;
    }

    /**
     * `value` is a list in `in` mode, absent in `null` mode, a single value
     * otherwise. Laravel does not nest `Rule::when()`, hence one condition
     * per case.
     *
     * @param list<Mode> $modes
     * @param list<mixed> $single
     *
     * @return list<mixed>
     */
    private function valueRules(
        string $name,
        array $modes,
        array $single,
    ): array {
        $in = \in_array(Mode::In, $modes, true);
        $null = \in_array(Mode::IsNull, $modes, true);
        if (!$in && !$null) {
            return ["required_with:{$name}", ...$single];
        }

        $mode = fn (Fluent $input): mixed => $input->get("{$name}.mode");
        $special = [Mode::In->value, Mode::IsNull->value];

        return [
            $null
                ? Rule::when(
                    fn (Fluent $input): bool => $mode($input)
                        === Mode::IsNull->value,
                    ['prohibited'],
                    ["required_with:{$name}"],
                )
                : "required_with:{$name}",
            ...$in
                ? [Rule::when(
                    fn (Fluent $input): bool => $mode($input) === Mode::In->value,
                    ['array', 'min:1'],
                )]
                : [],
            Rule::when(
                fn (Fluent $input): bool => !\in_array(
                    $mode($input),
                    $special,
                    true,
                ),
                $single,
            ),
        ];
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
