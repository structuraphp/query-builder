<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Closure;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;

/**
 * The validation of the scopes, for ValidationCompiler.
 */
final readonly class ScopeValidationCompiler
{
    public function __construct(
        private Definition $definition,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->definition->scopes as $name => [, $arguments]) {
            $arguments = ScopeApplier::fromRequest($arguments);
            $rules[$name] = $this->presence($name, $arguments);

            foreach ($arguments as $argument) {
                $rules = [...$rules, ...$this->argumentRules($name, $argument)];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->arguments() as $key => $argument) {
            foreach ($argument->messages as $rule => $message) {
                $messages["{$key}.{$rule}"] = $message;
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
        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach (ScopeApplier::fromRequest($arguments) as $argument) {
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
     * Every argument, context ones included.
     *
     * @return iterable<string, ScopeArgument> by `scope.argument` key
     */
    public function arguments(): iterable
    {
        foreach ($this->definition->scopes as $name => [, $arguments]) {
            foreach ($arguments as $argument) {
                yield "{$name}.{$argument->name}" => $argument;
            }
        }
    }

    /**
     * @param array<int, ScopeArgument> $arguments from the request
     *
     * @return list<mixed>
     */
    private function presence(string $name, array $arguments): array
    {
        if ($arguments === []) {
            return ['sometimes', 'required', 'boolean'];
        }

        $optional = array_all(
            $arguments,
            static fn (ScopeArgument $argument): bool => $argument->default
                instanceof Closure,
        );
        if (!$optional) {
            // `filled` rejects an empty object, which `required_with` would
            // treat as an absent scope.
            return ['array', 'filled'];
        }

        // Every argument has a default: `1` applies the scope with them, like
        // a scope without arguments.
        return [
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

    /**
     * @return array<string, list<mixed>>
     */
    private function argumentRules(
        string $name,
        ScopeArgument $argument,
    ): array {
        $key = "{$name}.{$argument->name}";
        $presence = $argument->default instanceof Closure
            ? ['sometimes', 'required']
            : ["required_with:{$name}"];
        $rules = [$key => [...$presence, ...$argument->rules]];

        $nested = $argument->nested;
        if ($argument->each !== []) {
            $nested['*'] = [...$argument->each, ...$nested['*'] ?? []];
        }

        // A nested `required` must not fire when the argument itself is
        // absent. A path under `*` then matches nothing, and the dependent
        // rule would make validation quadratic in the number of items.
        foreach ($nested as $path => $pathRules) {
            $rules["{$key}.{$path}"] = str_starts_with($path, '*')
                ? $pathRules
                : ["exclude_without:{$key}", ...$pathRules];
        }

        return $rules;
    }
}
