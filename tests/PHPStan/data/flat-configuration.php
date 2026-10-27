<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Validation\Rule;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;

class BaseQuery implements WithFilter
{
    public function filter(FilterInterface $filter): void
    {
        $filter->filter('title');
    }
}

final class CasedQuery implements WithFilter
{
    public function Filter(FilterInterface $filter): void
    {
        $this->helper($filter);
    }
}

final class StubQuery implements WithFilter
{
    public function filter(FilterInterface $filter): void
    {
        // nothing to configure
    }
}

final class InheritingQuery extends BaseQuery {}

final class NotAQuery
{
    public function filter(FilterInterface $filter, bool $on): void
    {
        if ($on) {
            $filter->filter('title');
        }
    }
}

final class FlatQuery implements WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('a')->attribute(from: 'b')->rules(['x' => ['y']]);
        $a = $scope->scope('b');
        $a->method('c');
        $scope->scope('d')->default(to: fn () => now());
        $scope->scope('e')->default(to: fn () => strtolower('X'));
        $scope->scope('f')->default(to: new \DateTimeImmutable());
        $scope->scope('g')->rules(['x' => [Rule::in(['a'])]]);
        $scope->scope('h')->default(
            to: static fn (string $scope) => strtolower($scope),
        );
        $a->default(x: fn ($a) => $a);
        $scope->scope('i')->default(to: function ($a, $scope) {
            return $a . $scope;
        });
        // trailing comment
    }
}

final class BrokenQuery extends BaseQuery implements WithScope
{
    private mixed $config = null;

    public function filter(FilterInterface $filter): void
    {
        if (true) {
            $filter->filter('a');
        }
        foreach (['a'] as $name) {
            $filter->filter('b');
        }
        match (true) {
            default => $filter->filter('c'),
        };
        try {
            $filter->filter('d');
        } catch (\Throwable) {
        }
        $this->helper($filter);
        helper($filter);
        parent::filter($filter);
        return;
    }

    public function scope(ScopeInterface $scope): void
    {
        $this->config = $scope->scope('a');
        $scope->scope('b')->default(to: fn () => $scope);
        $scope->scope('x')->attribute(from: parent::title());
        $scope->scope('c')->default(to: static function () use ($scope) {});
        $this->config->scope('d');
        $scope->scope('e')->default(to: fn () => [
            $scope,
            $scope,
        ]);
        $scope->scope('f')->default(to: fn () => [$scope, $scope]);
        $scope->scope('g')->default(to: [fn ($scope) => $scope, $scope]);
        $scope->scope('h')->default(to: new parent());
        $scope->scope('i')->default(to: $other::make());
        return;
    }

    private function helper(FilterInterface $filter): void {}
}

final class ReturningQuery implements WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        return $scope;
    }
}

function helper(FilterInterface $filter): void {}

final class TrailingReturnQuery implements WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('a');

        return;
    }
}

final class EarlyReturnQuery implements WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        return;
        $scope->scope('a');
    }
}

final class VariableVariableQuery implements WithScope
{
    public function scope(ScopeInterface $scope): void
    {
        ${'a'} = $scope->scope('a');
    }
}
