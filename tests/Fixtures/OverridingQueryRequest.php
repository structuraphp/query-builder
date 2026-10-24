<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Override;

class OverridingQueryRequest extends TestQueryRequest
{
    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return ['statut.value.in' => 'Surchargé.'];
    }
}
