<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use StructuraPhp\QueryBuilder\Contract\Query\FromModel;

/**
 * @implements FromModel<ProductBuilder>
 */
final class BrokenBuilderQuery implements FromModel
{
    public function getModel(): string
    {
        return Post::class;
    }
}
