<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;

final class ScopeContextQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('x1')->method('ofStatus')->context('status')
            ->cast(status: Type::String);
        $scope->scope('x2')->method('ofStatus')->context('status')
            ->rules(status: []);
        $scope->scope('x3')->method('ofStatus')->context('status')
            ->each(status: []);
        $scope->scope('x4')->method('ofStatus')->context('status')
            ->nested(status: []);
        $scope->scope('x5')->method('ofStatus')->context('status')
            ->messages(status: []);
        $scope->scope('x6')->method('ofStatus')->context('status')
            ->attribute(status: 'Status');
        $scope->scope('x7')->method('ofStatus')->context('status')
            ->attribute(status: ['a' => 'b']);
        $scope->scope('x8')
            ->method('ofStatus')
            ->context('status')
            ->messages(status: [])
            ->rules(status: [])
            ->cast(status: Status::class);
        $scope->scope('x9')
            ->method('ownedBy')
            ->context('author')
            ->cast(status: Type::String)
            ->rules(author: []);
        $scope->scope('x10')
            ->method('ownedBy')
            ->context('status')
            ->context('author')
            ->cast(status: Type::String);
        $scope->scope('x11')->method('ownedBy')->context('status');
        $scope->scope('ok')
            ->method('ownedBy')
            ->context('author')
            ->cast(status: Type::String)
            ->default(author: null);
    }
}
