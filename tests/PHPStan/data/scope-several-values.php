<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class SeveralValuesQuery implements FromModel, WithScope
{
    public function __construct(
        private readonly bool $flag,
        private readonly string $name,
    ) {}

    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope($this->flag ? 'missing' : 'lost');
        $scope->scope($this->name);
        $scope->scope('popular')->method($this->flag ? 'nope' : 'lost');
        $scope->scope('missing')
            ->method($this->flag ? 'popular' : 'minViews');
        $scope->scope('missing')->method('popular')->method($this->name);
        $scope->scope(name: 'gone');
        $scope->scope('popular')->method(method: 'away');
    }
}
