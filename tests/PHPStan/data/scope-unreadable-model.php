<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use stdClass;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

class StaticModelQuery implements FromModel, WithScope
{
    public const string MODEL = Article::class;

    public function getModel(): string
    {
        return static::MODEL;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class PropertyModelQuery implements FromModel, WithScope
{
    private string $model = Article::class;

    public function getModel(): string
    {
        return $this->model;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class BranchModelQuery implements FromModel, WithScope
{
    public function __construct(private readonly bool $flag) {}

    public function getModel(): string
    {
        if ($this->flag) {
            return Article::class;
        }

        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class TwoReturnsModelQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;

        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class EmptyReturnModelQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class NotModelQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return stdClass::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}

final class NonFlatScopeQuery implements FromModel, WithScope
{
    public function __construct(private readonly bool $flag) {}

    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
        if ($this->flag) {
            $scope->scope('lost');
        }
    }
}

final class DynamicCallQuery implements FromModel, WithScope
{
    public function __construct(private readonly string $call) {}

    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
        $scope->scope('popular')->{$this->call}('lost');
    }
}

final class UnpackedCallQuery implements FromModel, WithScope
{
    /**
     * @param list<literal-string> $names
     */
    public function __construct(private readonly array $names) {}

    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
        $scope->scope('popular')->method(...$this->names);
    }
}

final class CallableCallQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
        $scope->scope(...);
    }
}

abstract class AbstractModelQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('missing');
    }
}
