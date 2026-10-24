<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Illuminate\Validation\Rules\In;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\Service\ValidationCompiler;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\SortArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;
use Tests\StructuraPhp\QueryBuilder\TestCase;

/**
 * Documentation generators such as Scramble read the rules from a request
 * built with `new`.
 */
final class QueryRequestTest extends TestCase
{
    public function testRulesWithoutContainer(): void
    {
        TestQueryRequest::$queryClass = SortArticleQuery::class;

        $rules = new TestQueryRequest()->rules();

        self::assertArrayHasKey('sort', $rules);
    }

    public function testOneRulePerSortKey(): void
    {
        $definition = Definition::fromQuery(new SortArticleQuery());

        $rules = new ValidationCompiler($definition)->rules();

        self::assertArrayNotHasKey('sort.*', $rules);
        self::assertSame('sometimes', $rules['sort.date'][0] ?? null);
        self::assertInstanceOf(In::class, $rules['sort.title'][2] ?? null);
    }
}
