<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\FilterArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class FilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            [
                'title' => 'Foo',
                'status' => 'draft',
                'views' => 10,
                'published_at' => '2024-01-01',
                'rating' => 1.5,
                'featured' => true,
            ],
            [
                'title' => 'Foobar',
                'status' => 'published',
                'views' => 150,
                'published_at' => '2024-06-01',
                'rating' => 3.5,
                'featured' => false,
            ],
            [
                'title' => 'Baz',
                'status' => 'published',
                'views' => 300,
                'published_at' => '2025-01-01',
                'rating' => 4.5,
                'featured' => false,
            ],
        ]);
    }

    public function testEqualByDefault(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'Foo'],
        ]);

        $response->assertOk();
        self::assertSame(['Foo'], self::column($response, 'title'));
    }

    public function testLike(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'foo', 'mode' => 'like'],
        ]);

        $response->assertOk();
        self::assertSame(['Foo', 'Foobar'], self::column($response, 'title'));
    }

    public function testNotLike(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'foo', 'mode' => 'like', 'not' => 1],
        ]);

        $response->assertOk();
        self::assertSame(['Baz'], self::column($response, 'title'));
    }

    public function testComparison(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'views' => ['value' => 100, 'mode' => '>'],
        ]);

        $response->assertOk();
        self::assertSame(['Foobar', 'Baz'], self::column($response, 'title'));
    }

    public function testDateComparison(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'published_at' => ['value' => '2024-06-01', 'mode' => '>'],
        ]);

        $response->assertOk();
        self::assertSame(['Baz'], self::column($response, 'title'));
    }

    public function testAliasColumn(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'statut' => ['value' => 'draft'],
        ]);

        $response->assertOk();
        self::assertSame(['Foo'], self::column($response, 'title'));
    }

    public function testModeNotAllowedForType(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'Foo', 'mode' => '>'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title.mode');
    }

    public function testCustomRuleMessageAndAttribute(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'statut' => ['value' => 'foo'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'statut.value' => 'Statut invalide.',
            ]);
    }

    public function testAttributeDefaultsToPublicName(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'views' => ['value' => 'abc'],
        ]);

        $response->assertUnprocessable();
        self::assertStringContainsString(
            'views',
            self::errorMessage($response, 'views.value'),
        );
        self::assertStringNotContainsString(
            'views.value',
            self::errorMessage($response, 'views.value'),
        );
    }

    public function testScalarValueIs422(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, ['title' => 'foo']);

        $response->assertUnprocessable();
    }

    public function testInMode(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => ['Foo', 'Baz'], 'mode' => 'in'],
        ]);

        $response->assertOk();
        self::assertSame(['Foo', 'Baz'], self::column($response, 'title'));
    }

    public function testInModeCastsEachValue(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'views' => ['value' => ['10', '300'], 'mode' => 'in'],
        ]);

        $response->assertOk();
        self::assertSame(['Foo', 'Baz'], self::column($response, 'title'));
    }

    public function testNotInMode(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => [
                'value' => ['Foo', 'Baz'],
                'mode' => 'in',
                'not' => '1',
            ],
        ]);

        $response->assertOk();
        self::assertSame(['Foobar'], self::column($response, 'title'));
    }

    public function testInModeNeedsAnArray(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'Foo', 'mode' => 'in'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'title.value',
        ]);
    }

    public function testInModeValidatesEachValue(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'views' => ['value' => ['10', 'abc'], 'mode' => 'in'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'views.value.1',
        ]);
    }

    public function testInModeAppliesFilterRulesAndMessages(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'statut' => ['value' => ['draft', 'archived'], 'mode' => 'in'],
        ]);

        $response->assertUnprocessable();
        self::assertSame(
            'Statut invalide.',
            self::errorMessage($response, 'statut.value.1'),
        );
    }

    public function testDateHasNoInMode(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'published_at' => ['value' => ['2024-01-01'], 'mode' => 'in'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'published_at.mode',
        ]);
    }

    public function testNullMode(): void
    {
        DB::table('articles')->insert(['title' => 'Draft']);

        $response = $this->fetch(FilterArticleQuery::class, [
            'published_at' => ['mode' => 'null'],
        ]);

        $response->assertOk();
        self::assertSame(['Draft'], self::column($response, 'title'));
    }

    public function testNotNullMode(): void
    {
        DB::table('articles')->insert(['title' => 'Draft']);

        $response = $this->fetch(FilterArticleQuery::class, [
            'published_at' => ['mode' => 'null', 'not' => '1'],
        ]);

        $response->assertOk();
        self::assertSame(
            ['Foo', 'Foobar', 'Baz'],
            self::column($response, 'title'),
        );
    }

    public function testNullModeForbidsAValue(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'published_at' => ['mode' => 'null', 'value' => '2024-01-01'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'published_at.value',
        ]);
    }

    public function testNullModeNeedsANullableFilter(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['mode' => 'null'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'title.mode',
        ]);
    }

    public function testArrayValueIs422(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => ['a']],
        ]);

        $response->assertUnprocessable();
    }

    public function testUnknownRootKeyIsIgnored(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'utm_source' => 'x',
        ]);

        $response->assertOk();
        self::assertCount(3, self::column($response, 'title'));
    }

    public function testMissingValueIs422(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['mode' => 'like'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title.value');
    }

    public function testLikePercentIsLiteral(): void
    {
        DB::table('articles')->insert(['title' => '100%']);

        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => '%', 'mode' => 'like'],
        ]);

        $response->assertOk();
        self::assertSame(['100%'], self::column($response, 'title'));
    }

    public function testLikeUnderscoreIsLiteral(): void
    {
        DB::table('articles')->insert([['title' => 'a_b'], ['title' => 'axb']]);

        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'a_b', 'mode' => 'like'],
        ]);

        $response->assertOk();
        self::assertSame(['a_b'], self::column($response, 'title'));
    }

    public function testLikeBangIsLiteral(): void
    {
        DB::table('articles')->insert([['title' => 'a!b'], ['title' => 'axb']]);

        $response = $this->fetch(FilterArticleQuery::class, [
            'title' => ['value' => 'a!b', 'mode' => 'like'],
        ]);

        $response->assertOk();
        self::assertSame(['a!b'], self::column($response, 'title'));
    }

    public function testFloatFilter(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'rating' => ['value' => '3.5', 'mode' => '>='],
        ]);

        $response->assertOk();
        self::assertSame(['Foobar', 'Baz'], self::column($response, 'title'));
    }

    public function testBooleanFilterTrue(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'featured' => ['value' => '1'],
        ]);

        $response->assertOk();
        self::assertSame(['Foo'], self::column($response, 'title'));
    }

    public function testBooleanFilterFalse(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'featured' => ['value' => '0'],
        ]);

        $response->assertOk();
        self::assertSame(['Foobar', 'Baz'], self::column($response, 'title'));
    }

    public function testModeErrorUsesFilterAttribute(): void
    {
        $response = $this->fetch(FilterArticleQuery::class, [
            'statut' => ['value' => 'draft', 'mode' => '>'],
        ]);

        $response->assertUnprocessable();
        self::assertStringContainsString(
            'statut',
            self::errorMessage($response, 'statut.mode'),
        );
        self::assertStringNotContainsString(
            'statut.mode',
            self::errorMessage($response, 'statut.mode'),
        );
    }

    public function testRequestMessagesOverrideConfig(): void
    {
        TestQueryRequest::$queryClass = FilterArticleQuery::class;

        /** @var TestResponse<Response> $response */
        $response = $this->getJson(
            '/articles-override?' . http_build_query([
                'statut' => ['value' => 'foo'],
            ]),
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statut.value' => 'Surchargé.']);
    }
}
