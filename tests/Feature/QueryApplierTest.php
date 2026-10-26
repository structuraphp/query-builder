<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\FilterArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ScopeArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;
use UnexpectedValueException;

final class QueryApplierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['title' => 'Foo', 'status' => 'draft', 'views' => 500],
            ['title' => 'Bar', 'status' => 'published', 'views' => 5],
        ]);
    }

    public function testDisabledScopeDoesNotSkipTheNextOnes(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'popular' => '0',
            'statut' => ['status' => 'published'],
        ]);

        $response->assertOk();
        self::assertSame(['Bar'], self::column($response, 'title'));
    }

    public function testContextClosureNamesTheMissingRequestArgument(): void
    {
        $parameters = QueryParameters::fromArray(
            new ConfigurableQuery(scope: function (ScopeInterface $s): void {
                $s->scope('ofStatusAmong')->context('ids');
            }),
            ['ofStatusAmong' => ['status' => 'draft']],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Scope ofStatusAmong has no request argument foo for the context'
            . ' of ids.',
        );

        $parameters->builder([
            'ofStatusAmong' => ['ids' => fn (string $foo): array => []],
        ]);
    }

    public function testInModeCastsEachValue(): void
    {
        $parameters = QueryParameters::fromArray(new FilterArticleQuery(), [
            'views' => ['value' => ['10', '300'], 'mode' => 'in'],
        ]);

        $bindings = $parameters->builder()->getQuery()->getBindings();

        self::assertSame([10, 300], $bindings);
    }

    public function testUnvalidatedListOutsideInModeIsABug(): void
    {
        $parameters = new QueryParameters(
            Definition::fromQuery(new FilterArticleQuery()),
            ['title' => ['value' => ['Foo']]],
        );

        $this->expectException(UnexpectedValueException::class);

        $parameters->builder();
    }
}
