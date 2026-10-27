<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Author;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;

final class ScopeCastsQuery implements FromModel, WithScope
{
    private const string AUTHOR = 'Tests\StructuraPhp\QueryBuilder'
        . '\Fixtures\Author';

    private const string STATUS = 'Tests\StructuraPhp\QueryBuilder'
        . '\Fixtures\Status';

    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('k1')->method('near')->cast(
            lat: Type::Float,
            lng: Author::class,
        );
        $scope->scope('k2')->method('near')->cast(
            lat: Status::class,
            lng: fn (mixed $value): mixed => $value,
        );
        $scope->scope('k3')->method('near')
            ->cast(lat: Type::Float, lng: 'nope');
        $scope->scope('k4')->method('near')
            ->cast(lat: Type::Float, lng: self::AUTHOR);
        $scope->scope('k5')->method('near')
            ->cast(lat: Type::Float, lng: Nowhere::class);
        $scope->scope('k6')->method('near')
            ->cast(lat: Type::Float, lng: self::STATUS);
        $scope->scope('k7')->method('near')->cast(lat: Type::Float)
            ->cast(lng: self::unknown());
        $scope->scope('k8')->method('near')
            ->cast(lat: self::unknown(), lng: Type::Float);
        $scope->scope('k9')
            ->method('ownedBy')
            ->cast(status: Author::class)
            ->cast(author: Type::Float);
        $scope->scope('k10')
            ->method('ownedBy')
            ->cast(status: Type::String, author: Author::class)
            ->cast(author: Status::class);
        $scope->scope('k11')
            ->method('ownedBy')
            ->cast(status: Author::class)
            ->cast(status: Type::String)
            ->cast(author: Type::Float);
        $scope->scope('k12')->method('near')
            ->cast(
                lat: fn (mixed $value): mixed => $value,
                lng: Author::class,
            );
        $scope->scope('k13')->method('near')->cast(lat: Direction::Asc);
        $scope->scope('o1')
            ->method('ofStatus')
            ->cast(status: Author::class)
            ->rules(foo: []);
        $scope->scope('m1')
            ->method('near')
            ->method('popular')
            ->cast(lat: Type::Float);
    }

    private static function unknown(): Type
    {
        return Type::String;
    }
}
