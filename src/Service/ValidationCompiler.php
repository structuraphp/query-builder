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
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\ValueObject\Config\FilterConfig;
use Throwable;

final readonly class ValidationCompiler
{
    private ScopeValidationCompiler $scopes;

    public function __construct(
        private Definition $definition,
    ) {
        $this->scopes = new ScopeValidationCompiler($definition);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            ...$this->scopes->rules(),
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
        // Precognition drops the rules of the fields it does not validate.
        $rules = $validator->getRules();

        foreach ($this->scopes->arguments() as $key => $argument) {
            $failed = array_any(
                $errors->keys(),
                static fn (string $error): bool => $error === $key
                    || str_starts_with($error, "{$key}."),
            );
            if (
                !$argument->check instanceof Closure
                || $failed
                || !isset($rules[$key])
                || !Arr::has($data, $key)
            ) {
                continue;
            }

            try {
                ($argument->check)(Arr::get($data, $key));
            } catch (Throwable) {
                $errors->add($key, $this->invalid($validator, $key));
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

        return [...$messages, ...$this->scopes->messages()];
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

        return [...$attributes, ...$this->scopes->attributes()];
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

            $single = $this->single($config, $type);

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
     * The rules of a single value.
     *
     * @return list<mixed>
     */
    private function single(FilterConfig $config, Type $type): array
    {
        // A long `like` pattern can be refused by the engine (SQLite).
        $length = $type === Type::String
            ? ["max:{$config->maxLength}"]
            : [];

        return [...TypeCaster::rules($type), ...$length, ...$config->rules];
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
            ...$this->listRules(
                'count',
                array_keys($this->definition->counts),
            ),
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
        $errors->add($key, $this->invalid($validator, $key));
    }

    private function invalid(Validator $validator, string $key): string
    {
        $message = trans('validation.in', [
            'attribute' => $validator->getDisplayableAttribute($key),
        ]);

        return \is_string($message) ? $message : 'validation.in';
    }
}
