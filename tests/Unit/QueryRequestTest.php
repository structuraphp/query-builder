<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\In;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Service\Definition;
use StructuraPhp\QueryBuilder\Service\ValidationCompiler;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\FilterArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ScopeArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\SortArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\RequiringQueryRequest;
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

    public function testOwnRuleOnAnAbsentParameterStillApplies(): void
    {
        TestQueryRequest::$queryClass = SortArticleQuery::class;

        $response = $this->getJson('/articles-requiring');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant');
    }

    public function testOwnRuleOnAGivenParameterStillApplies(): void
    {
        TestQueryRequest::$queryClass = SortArticleQuery::class;

        $response = $this->getJson('/articles-requiring?tenant=abc');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant');
    }

    /**
     * Scramble reads `required` without `sometimes` as a required parameter,
     * yet every query parameter is optional.
     *
     * @param class-string<FromModel> $query
     */
    #[DataProvider('queries')]
    public function testRequiredRulesStayOptional(string $query): void
    {
        TestQueryRequest::$queryClass = $query;

        $rules = new TestQueryRequest()->rules();

        foreach ($rules as $key => $keyRules) {
            if (\in_array('required', $keyRules, true)) {
                self::assertContains('sometimes', $keyRules, $key);
            }
        }
    }

    /**
     * @return iterable<string, array{class-string<FromModel>}>
     */
    public static function queries(): iterable
    {
        yield 'scopes' => [ScopeArticleQuery::class];

        yield 'filters' => [FilterArticleQuery::class];

        yield 'sorts' => [SortArticleQuery::class];
    }

    #[Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::get(
            '/articles-requiring',
            static fn (RequiringQueryRequest $request): int => 200,
        );
    }
}
