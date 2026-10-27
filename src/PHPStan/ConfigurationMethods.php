<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Reflection\ClassReflection;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;

/**
 * Which methods of a query class configure it.
 */
final class ConfigurationMethods
{
    /** Each configuration method, and the contract that declares it. */
    public const array SECTIONS = [
        'filter' => WithFilter::class,
        'scope' => WithScope::class,
        'sort' => WithSort::class,
        'relation' => WithRelation::class,
        'paginate' => WithPaginate::class,
    ];

    public static function isConfiguration(
        ClassReflection $class,
        string $method,
    ): bool {
        $contract = self::SECTIONS[strtolower($method)] ?? null;

        return $contract !== null && $class->implementsInterface($contract);
    }
}
