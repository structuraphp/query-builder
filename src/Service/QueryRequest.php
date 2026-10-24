<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

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
        return new QueryParameters(
            $this->definition(),
            $this->validatedQuery(),
            $this->queryData(),
        );
    }

    /**
     * @return class-string<FromModel>
     */
    abstract protected function getQuery(): string;

    protected function definition(): Definition
    {
        return $this->definition ??= Definition::fromQuery(
            $this->container->make($this->getQuery()),
        );
    }

    #[Override]
    protected function createDefaultValidator(
        ValidationFactory $factory,
    ): Validator {
        $compiler = new ValidationCompiler($this->definition());

        $validator = $factory->make(
            $this->validationData(),
            $this->validationRules(),
            array_merge($compiler->messages(), $this->messages()),
            array_merge($compiler->attributes(), $this->attributes()),
        );

        if ($validator instanceof \Illuminate\Validation\Validator) {
            $validator->stopOnFirstFailure($this->stopOnFirstFailure);

            if ($this->isPrecognitive()) {
                $validator->setRules($this->filterPrecognitiveRules(
                    $validator->getRulesWithoutPlaceholders(),
                ));
            }
        }

        return $validator;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedQuery(): array
    {
        /** @var array<string, mixed> */
        return $this->validator->validated();
    }

    /**
     * @return array<string, mixed>
     */
    private function queryData(): array
    {
        /** @var array<string, mixed> */
        return $this->validationData();
    }
}
