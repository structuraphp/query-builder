<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Override;

/**
 * A request customised the way an application would: its own attribute
 * names, a message built from the query definition, one error at a time.
 */
class PaginationQueryRequest extends TestQueryRequest
{
    protected $stopOnFirstFailure = true;

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return ['limit' => 'page size'];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        $max = $this->definition()->pagination->maxLimit;

        return ['limit.max' => "The :attribute cannot exceed {$max}."];
    }
}
