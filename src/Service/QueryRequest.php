<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Override;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;

/**
 * @template TBuilder of Builder<covariant Model> = Builder<Model>
 */
abstract class QueryRequest extends FormRequest
{
    private ?Definition $definition = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return new ValidationCompiler($this->definition())->rules();
    }

    /**
     * Only the query string: a JSON body on a GET request would bypass PHP's
     * `max_input_vars` and the length limit of the URL.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function validationData(): array
    {
        /** @var array<string, mixed> */
        return $this->query->all();
    }

    /**
     * @return QueryParameters<TBuilder>
     */
    public function parameters(): QueryParameters
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validator->validated();

        /** @var array<string, mixed> $data */
        $data = $this->validationData();

        /** @var QueryParameters<TBuilder> $parameters */
        $parameters = new QueryParameters(
            $this->definition(),
            $validated,
            $data,
        );

        return $parameters;
    }

    /**
     * Public for the documentation generators, which read what the query
     * declares.
     *
     * Not `$this->container`: they build the request with `new`, without
     * setting it.
     */
    public function definition(): Definition
    {
        return $this->definition ??= Definition::fromQuery(
            Container::getInstance()->make($this->getQuery()),
        );
    }

    /**
     * @return class-string<FromModel<TBuilder>>
     */
    abstract protected function getQuery(): string;

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
