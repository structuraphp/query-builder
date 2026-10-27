<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Closure;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Author;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;

enum Plain
{
    case A;
}

class TypedScopesArticle extends Article
{
    /**
     * @param Builder<self> $query
     */
    public function scopePlain(
        Builder $query,
        int $i,
        float $f,
        bool $b,
        string $s,
        array $a,
        ?int $n,
        int|null $m,
    ): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeDates(
        Builder $query,
        DateTimeInterface $a,
        CarbonInterface $b,
        DateTimeImmutable $c,
        DateTime $d,
        Carbon $e,
        CarbonImmutable $f,
    ): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeEnums(Builder $query, Status $a, ?Status $b): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeBadEnum(Builder $query, \BackedEnum $a): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeUnit(Builder $query, Plain $a): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopePlainObject(Builder $query, Closure $a): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeLoose(Builder $query, mixed $a): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeMissing(Builder $query, Nowhere $a): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeUnionDate(Builder $query, Carbon|string $a): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeBoth(
        Builder $query,
        Status $a,
        $b,
        int $c,
    ): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeScalars(Builder $query, int $a, $b): void {}

    /**
     * @param Builder<self> $query
     */
    public function scopeSelf(Builder $query, self $a): void {}
}

final class ScopeTypesQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return TypedScopesArticle::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('plain');
        $scope->scope('dates');
        $scope->scope('enums');
        $scope->scope('badEnum');
        $scope->scope('unit');
        $scope->scope('plainObject');
        $scope->scope('loose');
        $scope->scope('missing');
        $scope->scope('unionDate');
        $scope->scope('both');
        $scope->scope('self');
        $scope->scope('scalars');
    }
}
