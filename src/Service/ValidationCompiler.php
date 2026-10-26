<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\Mode;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;
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
     * Drops the rules this query declares for parameters absent from the
     * data: every parameter is optional, so they cannot fail, yet each one
     * costs the validator a pass. Other rules are kept.
     *
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function forData(array $rules, array $data): array
    {
        $declared = $this->rules();

        return array_filter(
            $rules,
            static fn (string $key): bool => !isset($declared[$key])
                || \array_key_exists(explode('.', $key)[0], $data),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Runs closure casts once the rules of their argument passed, so a cast
     * only sees valid data. A cast that throws is a validation error.
     */
    public function after(Validator $validator): void
    {
        $this->checkCursorSorts($validator);

        $errors = $validator->errors();
        $data = $validator->getData();

        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach ($arguments as $argument) {
                $key = "{$name}.{$argument->name}";
                $failed = array_any(
                    $errors->keys(),
                    static fn (string $error): bool => $error === $key
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
        // The `array:` message only says that `sort` must be an array, even
        // for an undeclared key.
        $invalid = trans('validation.in');
        $messages = \is_string($invalid) ? ['sort.array' => $invalid] : [];
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
            $rules[$name] = ['array', 'filled'];
            $optional = array_all(
                $arguments,
                static fn (ScopeArgument $argument): bool => $argument->default
                    instanceof Closure,
            );
            if ($optional) {
                // Every argument has a default: `1` applies the scope with
                // them, like a scope without arguments.
                $rules[$name] = [
                    'sometimes',
                    'required',
                    Rule::when(
                        static fn (Fluent $input): bool => \is_array(
                            $input->get($name),
                        ),
                        ['array', 'filled'],
                        ['boolean'],
                    ),
                ];
            }

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
                // is absent. A path under `*` then matches nothing, and the
                // dependent rule would make validation quadratic in the
                // number of items.
                foreach ($nested as $path => $pathRules) {
                    $rules["{$key}.{$path}"] = str_starts_with($path, '*')
                        ? $pathRules
                        : ["exclude_without:{$key}", ...$pathRules];
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

            $modes = FilterApplier::modes($type);
            if ($config->nullable) {
                $modes[] = Mode::IsNull;
            }

            $single = array_merge(TypeCaster::rules($type), $config->rules);

            $rules[$name] = ['array'];
            $rules["{$name}.value"] = $this->valueRules(
                $name,
                $modes,
                $single,
                $config->maxItems,
            );
            if (\in_array(Mode::In, $modes, true)) {
                // In `in` mode, each item gets the rules of a single value.
                $rules["{$name}.value.*"] = $single;
            }

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
        int $maxItems,
    ): array {
        $in = \in_array(Mode::In, $modes, true);
        $null = \in_array(Mode::IsNull, $modes, true);
        if (!$in && !$null) {
            return ["required_with:{$name}", ...$single];
        }

        $mode = static fn (Fluent $input): mixed => $input->get("{$name}.mode");
        $special = [Mode::In->value, Mode::IsNull->value];

        return [
            $null
                ? Rule::when(
                    static fn (Fluent $input): bool => $mode($input)
                        === Mode::IsNull->value,
                    ['prohibited'],
                    ["required_with:{$name}"],
                )
                : "required_with:{$name}",
            ...$in
                ? [Rule::when(
                    static fn (Fluent $input): bool => $mode($input)
                        === Mode::In->value,
                    ['array', 'min:1', "max:{$maxItems}"],
                )]
                : [],
            Rule::when(
                static fn (Fluent $input): bool => !\in_array(
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

        // `array:` keeps the client's key order in validated(), which sets
        // the priority. One rule per key rather than `sort.*`, so
        // documentation generators list each sort.
        $rules = ['sort' => ['array:' . implode(',', $names)]];
        foreach ($names as $name) {
            $rules["sort.{$name}"] = [
                'sometimes',
                'required',
                Rule::in(Direction::cases()),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function relationRules(): array
    {
        return [
            ...$this->listRules(
                'with',
                array_keys($this->definition->relations),
            ),
            ...$this->listRules('count', $this->definition->counts),
        ];
    }

    /**
     * A comma-separated list of declared names (`with`, `count`).
     *
     * @param list<string> $declared
     *
     * @return array<string, list<mixed>>
     */
    private function listRules(string $key, array $declared): array
    {
        if ($declared === []) {
            return [$key => ['prohibited']];
        }

        $rule = static function (
            string $attribute,
            mixed $value,
            Closure $fail,
        ) use ($declared): void {
            /** @var Closure(string): PotentiallyTranslatedString $fail */
            $with = \is_string($value) ? $value : '';
            $unknown = array_any(
                QueryApplier::relationNames($with),
                static fn (string $name): bool => !\in_array(
                    $name,
                    $declared,
                    true,
                ),
            );
            if ($unknown) {
                $fail('validation.in')->translate();
            }
        };

        return [$key => ['string', $rule]];
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

    /**
     * Cursor pagination cannot compare a computed count in its WHERE clause:
     * the client cannot combine it with a count sort, even a default one.
     */
    private function checkCursorSorts(Validator $validator): void
    {
        $errors = $validator->errors();
        if ($errors->hasAny(['pagination', 'sort', 'sort.*'])) {
            return;
        }

        $data = $validator->getData();
        $mode = $data['pagination']
            ?? $this->definition->pagination->default->value;
        if ($mode !== PaginationMode::Cursor->value) {
            return;
        }

        $sort = $data['sort'] ?? null;
        $names = \is_array($sort)
            ? array_keys($sort)
            : array_column($this->definition->defaultSorts, 0);
        $counted = array_any(
            $names,
            fn (int|string $name): bool => ($this->definition
                ->sorts[$name] ?? null)?->count !== null,
        );
        if (!$counted) {
            return;
        }

        $key = \is_array($sort) ? 'sort' : 'pagination';
        $message = trans('validation.in', [
            'attribute' => $validator->getDisplayableAttribute($key),
        ]);
        $errors->add($key, \is_string($message) ? $message : 'validation.in');
    }
}
