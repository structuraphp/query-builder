<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Override;

class ValidationDataQueryRequest extends TestQueryRequest
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function validationData(): array
    {
        /** @var array<string, mixed> $data */
        $data = parent::validationData();

        return [...$data, 'minViews' => ['foo' => 1]];
    }
}
