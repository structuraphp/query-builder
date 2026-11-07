<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use StructuraPhp\QueryBuilder\Service\QueryRequest;

/**
 * @extends QueryRequest<ProductBuilder>
 */
final class ProductRequest extends QueryRequest
{
    protected function getQuery(): string
    {
        return ProductQuery::class;
    }
}
