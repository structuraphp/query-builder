<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Override;

/**
 * Adds a rule of its own on a parameter the query does not declare.
 */
class RequiringQueryRequest extends TestQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    public function rules(): array
    {
        return [...parent::rules(), 'tenant' => ['required', 'integer']];
    }
}
