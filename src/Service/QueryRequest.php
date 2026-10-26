<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Override;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;

abstract class QueryRequest extends FormRequest
{
    private Definition $definition;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return new ValidationCompiler($this->definition())->rules();
    }

    public function parameters(): QueryParameters
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validator->validated();

        /** @var array<string, mixed> $data */
        $data = $this->validationData();

        return new QueryParameters($this->definition(), $validated, $data);
    }

    /**
     * @return class-string<FromModel>
     */
    abstract protected function getQuery(): string;

    /**
     * Not `$this->container`: documentation generators (Scramble) build the
     * request with `new`, without setting it.
     */
    protected function definition(): Definition
    {
        return $this->definition ??= Definition::fromQuery(
            Container::getInstance()->make($this->getQuery()),
        );
    }

    #[Override]
    protected function createDefaultValidator(
        ValidationFactory $factory,
    ): Validator {
        $compiler = new ValidationCompiler($this->definition());

        /** @var array<string, mixed> $data */
        $data = $this->validationData();

        /** @var array<string, mixed> $rules */
        $rules = $this->validationRules();
        $validator = $factory->make(
            $data,
            $compiler->forData($rules, $data),
            array_merge($compiler->messages(), $this->messages()),
            array_merge($compiler->attributes(), $this->attributes()),
        );

        if ($validator instanceof \Illuminate\Validation\Validator) {
            $validator->after($compiler->after(...));
            $validator->stopOnFirstFailure($this->stopOnFirstFailure);

            if ($this->isPrecognitive()) {
                $validator->setRules($this->filterPrecognitiveRules(
                    $validator->getRulesWithoutPlaceholders(),
                ));
            }
        }

        return $validator;
    }
}
