<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class ScopeOptionsQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('c1')->method('popular')->cast(foo: Type::String);
        $scope->scope('c2')->method('popular')->default(foo: 1);
        $scope->scope('c3')->method('popular')->rules(foo: ['max:2']);
        $scope->scope('c4')->method('popular')->each(foo: ['max:2']);
        $scope->scope('c5')->method('popular')->nested(foo: []);
        $scope->scope('c6')->method('popular')->messages(foo: []);
        $scope->scope('c7')->method('popular')->attribute(foo: 'Foo');
        $scope->scope('c8')->method('popular')->context('foo');
        $scope->scope('c9')->method('popular')->attribute(foo: ['a' => 'b']);
        $scope->scope('popular');
        $scope->scope('c10')
            ->method('popular')
            ->rules(zzz: [])
            ->attribute(bar: 'Bar')
            ->context('yyy')
            ->cast(foo: Type::String);
        $scope->scope('c11')
            ->method('popular')
            ->attribute(zzz: ['a' => 'b'])
            ->attribute(aaa: 'Aaa');
        $scope->scope('c13')
            ->method('popular')
            ->attribute(aaa: 'Aaa')
            ->messages(mmm: []);
        $scope->scope('c12')
            ->method('ofStatus')
            ->cast(status: Type::String, bar: Type::Integer)
            ->rules(status: [], foo: [])
            ->cast(foo: Type::Integer);
        $scope->scope('p1')->method('popular')->rules(['max:2']);
        $scope->scope('p2')->method('popular')->default(1);
        $scope->scope('p3')->method('popular')->cast(foo: Type::String)
            ->rules(['a']);
        $scope->scope('p4')->method('popular')->attribute(['x']);
        $scope->scope('p5')
            ->method('ofStatus')
            ->context('status')
            ->each(status: [])
            ->cast(Type::String);
        $scope->scope('v1')
            ->method('popular')
            ->rules(zzz: [])
            ->attribute(foo: self::label());
        $scope->scope('v2')
            ->method('ofStatus')
            ->attribute(status: self::label())
            ->rules(zzz: []);
        $scope->scope('ok1')->method('ofStatus')->context('status');
        $scope->scope('ok2')->method('ofStatus')->context(status: 'status');
        $scope->scope('ok3')->method('ofStatus')->default(status: null);
    }

    private static function label(): string
    {
        return 'Label';
    }
}
