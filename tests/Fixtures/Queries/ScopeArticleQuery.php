<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures\Queries;

use Carbon\CarbonImmutable;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Enum\Type;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

final class ScopeArticleQuery implements FromModel, WithScope
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');
        $scope->scope('publishedBetween')
            ->default(
                to: fn (): CarbonImmutable => CarbonImmutable::parse(
                    '2030-01-01',
                ),
            )
            ->attribute(from: 'date de début');
        $scope->scope('statut')->method('ofStatus');
        $scope->scope('ofStatuses')
            ->rules(statuses: ['max:2'])
            ->each(statuses: ['in:draft,published']);
        $scope->scope('minViews');
        $scope->scope('near')->cast(lat: Type::Float, lng: Type::Float);
    }
}
