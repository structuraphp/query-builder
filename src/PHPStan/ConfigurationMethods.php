<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Reflection\ClassReflection;
use StructuraPhp\QueryBuilder\Contract\Config\FilterConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
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
    /** @var array<string, class-string> */
    public const array SECTIONS = [
        'filter' => FilterInterface::class,
        'scope' => ScopeInterface::class,
        'sort' => SortInterface::class,
        'relation' => RelationInterface::class,
        'paginate' => PaginationInterface::class,
    ];

    /** @var list<class-string> */
    public const array CONFIG_TYPES = [
        FilterInterface::class,
        FilterConfigInterface::class,
        ScopeInterface::class,
        ScopeConfigInterface::class,
        SortInterface::class,
        SortConfigInterface::class,
        RelationInterface::class,
        RelationConfigInterface::class,
        PaginationInterface::class,
    ];

    private const array CONTRACTS = [
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
        $contract = self::CONTRACTS[strtolower($method)] ?? null;

        return $contract !== null && $class->implementsInterface($contract);
    }
}
