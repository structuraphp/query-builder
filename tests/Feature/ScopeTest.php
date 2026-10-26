<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\CountingCastArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ScopeArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class ScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            [
                'title' => 'Old',
                'status' => 'draft',
                'views' => 5,
                'published_at' => '2023-01-01',
            ],
            [
                'title' => 'Mid',
                'status' => 'published',
                'views' => 50,
                'published_at' => '2024-06-01',
            ],
            [
                'title' => 'New',
                'status' => 'published',
                'views' => 500,
                'published_at' => '2029-01-01',
            ],
        ]);
    }

    public function testNoArgScopeApplied(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, ['popular' => 1]);

        $response->assertOk();
        self::assertSame(['New'], self::column($response, 'title'));
    }

    public function testNoArgScopeSkippedWhenFalse(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, ['popular' => 0]);

        $response->assertOk();
        self::assertCount(3, self::column($response, 'title'));
    }

    public function testNoArgScopeRejectsNonBoolean(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'popular' => 'abc',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('popular');
    }

    public function testNamedArgumentsAnyOrder(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => [
                'to' => '2024-12-31',
                'from' => '2024-01-01',
            ],
        ]);

        $response->assertOk();
        self::assertSame(['Mid'], self::column($response, 'title'));
    }

    public function testConfigDefaultFillsMissingArgument(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => ['from' => '2024-01-01'],
        ]);

        $response->assertOk();
        self::assertSame(['Mid', 'New'], self::column($response, 'title'));
    }

    public function testRequiredArgumentMissingIs422(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => ['to' => '2024-01-01'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publishedBetween.from');
        self::assertStringContainsString(
            'date de début',
            self::errorMessage($response, 'publishedBetween.from'),
        );
    }

    public function testPhpDefault(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'minViews' => ['foo' => 1],
        ]);

        $response->assertOk();
        self::assertSame(['Mid', 'New'], self::column($response, 'title'));
    }

    public function testExplicitArgumentOverridesPhpDefault(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'minViews' => ['min' => 100],
        ]);

        $response->assertOk();
        self::assertSame(['New'], self::column($response, 'title'));
    }

    public function testAliasMethodAndEnum(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'statut' => ['status' => 'draft'],
        ]);

        $response->assertOk();
        self::assertSame(['Old'], self::column($response, 'title'));
    }

    public function testInvalidEnumIs422(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'statut' => ['status' => 'foo'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut.status');
    }

    public function testArrayArgument(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'ofStatuses' => ['statuses' => ['draft']],
        ]);

        $response->assertOk();
        self::assertSame(['Old'], self::column($response, 'title'));
    }

    public function testArrayArgumentEachRule(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'ofStatuses' => ['statuses' => ['foo']],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ofStatuses.statuses.0');
    }

    public function testArrayArgumentRule(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'ofStatuses' => ['statuses' => ['draft', 'draft', 'draft']],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ofStatuses.statuses');
    }

    public function testForcedCast(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'near' => ['lat' => '10', 'lng' => '100'],
        ]);

        $response->assertOk();
        self::assertSame(['Mid'], self::column($response, 'title'));
    }

    public function testForcedCastValidatesType(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'near' => ['lat' => 'x', 'lng' => '100'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('near.lat');
    }

    public function testInfiniteFloatArgumentIsInvalid(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'near' => ['lat' => '1e999', 'lng' => '100'],
        ]);

        $response->assertUnprocessable();
        self::assertSame(
            'The near.lat field must be a number.',
            self::errorMessage($response, 'near.lat'),
        );
    }

    public function testBadDateIs422(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => ['from' => 'tomorrow'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publishedBetween.from');
    }

    public function testScopeWithOnlyDefaultsTakesABoolean(): void
    {
        $enabled = $this->fetch(ScopeArticleQuery::class, ['minViews' => '1']);
        $disabled = $this->fetch(ScopeArticleQuery::class, ['minViews' => '0']);

        $enabled->assertOk();
        $disabled->assertOk();
        self::assertSame(['Mid', 'New'], self::column($enabled, 'title'));
        self::assertCount(3, self::column($disabled, 'title'));
    }

    public function testScopeWithOnlyDefaultsRejectsANonBoolean(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'minViews' => 'abc',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('minViews');
    }

    public function testScopeWithARequiredArgumentRejectsABoolean(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => '1',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publishedBetween');
    }

    public function testScalarForArgScopeIs422(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => '2024',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publishedBetween');
    }

    public function testSeveralScopesInOneRequest(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'publishedBetween' => ['from' => '2020-01-01'],
            'minViews' => ['foo' => 1],
        ]);

        $response->assertOk();
        self::assertSame(['Mid', 'New'], self::column($response, 'title'));
    }

    public function testAbsentScopeNotApplied(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class);

        $response->assertOk();
        self::assertCount(3, self::column($response, 'title'));
    }

    public function testArgumentAttributeDefaultsToDottedName(): void
    {
        $response = $this->fetch(ScopeArticleQuery::class, [
            'ofStatuses' => ['statuses' => ['draft', 'draft', 'draft']],
        ]);

        $response->assertUnprocessable();
        self::assertStringContainsString(
            'ofStatuses.statuses',
            self::errorMessage($response, 'ofStatuses.statuses'),
        );
    }

    public function testPresenceUsesValidationData(): void
    {
        TestQueryRequest::$queryClass = ScopeArticleQuery::class;

        $response = $this->getJson('/articles-data');

        $response->assertOk();
        self::assertSame(['Mid', 'New'], self::column($response, 'title'));
    }

    public function testClosureCastRunsOncePerValue(): void
    {
        CountingCastArticleQuery::$calls = 0;

        $response = $this->fetch(CountingCastArticleQuery::class, [
            'near' => ['lat' => '10', 'lng' => '100'],
        ]);

        $response->assertOk();
        self::assertSame(1, CountingCastArticleQuery::$calls);
    }

    public function testPrecognitionValidatesOnlyRequestedKeys(): void
    {
        TestQueryRequest::$queryClass = ScopeArticleQuery::class;

        $response = $this->withHeaders([
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'limit',
        ])
            ->getJson(
                '/articles-precog?' . http_build_query([
                    'popular' => 'abc',
                    'limit' => 10,
                ]),
            );

        $response->assertNoContent();
    }

    /**
     * A query string cannot send an empty array: it comes from code, through
     * fromArray().
     */
    public function testPrecognitionDoesNotCastUnvalidatedArguments(): void
    {
        TestQueryRequest::$queryClass = CountingCastArticleQuery::class;
        CountingCastArticleQuery::$calls = 0;

        $response = $this->withHeaders([
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'limit',
        ])->getJson('/articles-precog?' . http_build_query([
            'near' => ['lat' => ['x' => '1'], 'lng' => '100'],
            'limit' => 10,
        ]));

        $response->assertNoContent();
        self::assertSame(0, CountingCastArticleQuery::$calls);
    }

    public function testEmptyArgumentsForScopeAreInvalid(): void
    {
        $this->expectException(ValidationException::class);

        QueryParameters::fromArray(new ScopeArticleQuery(), [
            'publishedBetween' => [],
        ]);
    }

    public function testEmptyArgumentsForEnumScopeAreInvalid(): void
    {
        $this->expectException(ValidationException::class);

        QueryParameters::fromArray(new ScopeArticleQuery(), ['statut' => []]);
    }

    public function testJsonBodyIsIgnored(): void
    {
        TestQueryRequest::$queryClass = ScopeArticleQuery::class;

        $response = $this->json('GET', '/articles?popular=1', [
            'popular' => 0,
            'minViews' => ['min' => 1000],
        ]);

        $response->assertOk();
        self::assertSame(['New'], self::column($response, 'title'));
    }
}
