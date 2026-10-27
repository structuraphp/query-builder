<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Smoke;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Product>
 */
final class ProductBuilder extends Builder {}
