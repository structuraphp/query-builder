<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Carbon\CarbonImmutable;
use PHPStan\Testing\PHPStanTestCase;
use StructuraPhp\QueryBuilder\PHPStan\ModelInspector;
use StructuraPhp\QueryBuilder\PHPStan\ScopeSignature;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\SecretViewsArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\HiddenArrayArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\HiddenTraitArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\LooseBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\MissingBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\MixedHiddenArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\NamedBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\StringHiddenArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\UnsetHiddenArticle;

final class ModelInspectorTest extends PHPStanTestCase
{
    public function testModelScopeDropsTheQuery(): void
    {
        $model = $this->inspect(Article::class);

        $date = CarbonImmutable::class;

        self::assertEquals(new ScopeSignature([
            ['name' => 'from', 'type' => $date],
            ['name' => 'to', 'type' => $date],
        ]), $model->findScope('publishedBetween'));
        self::assertEquals(new ScopeSignature([
            ['name' => 'min', 'type' => 'int'],
        ]), $model->findScope('minViews'));
        self::assertEquals(new ScopeSignature([
            ['name' => 'value', 'type' => null],
        ]), $model->findScope('union'));
        self::assertNull($model->findScope('missing'));
        self::assertTrue($model->knowsScopes());
    }

    public function testBuilderMethodKeepsItsParameters(): void
    {
        self::assertEquals(new ScopeSignature([
            ['name' => 'min', 'type' => 'int'],
        ]), $this->inspect(BuiltArticle::class)->findScope('viewsAtLeast'));
        self::assertEquals(new ScopeSignature([
            ['name' => 'min', 'type' => 'int'],
            ['name' => 'max', 'type' => 'int'],
        ]), $this->inspect(NamedBuilderArticle::class)
            ->findScope('viewsBetween'));
    }

    public function testUnknownBuilderFindsNoScope(): void
    {
        $model = $this->inspect(LooseBuilderArticle::class);

        self::assertFalse($model->knowsScopes());
        self::assertNull($model->findScope('missing'));
        self::assertFalse(
            $this->inspect(MissingBuilderArticle::class)->knowsScopes(),
        );
    }

    public function testRelationIsAModelMethod(): void
    {
        $model = $this->inspect(Article::class);

        self::assertTrue($model->hasRelation('author'));
        self::assertFalse($model->hasRelation('missing'));
    }

    public function testHiddenColumns(): void
    {
        self::assertSame([], $this->inspect(Article::class)->hiddenColumns());
        self::assertSame(
            ['views'],
            $this->inspect(SecretViewsArticle::class)->hiddenColumns(),
        );
        self::assertSame(
            ['views', 'secret', 'token'],
            $this->inspect(HiddenArrayArticle::class)->hiddenColumns(),
        );
        self::assertSame(
            ['secret', 'token'],
            $this->inspect(HiddenTraitArticle::class)->hiddenColumns(),
        );
    }

    public function testHiddenThatIsNotAListOfColumns(): void
    {
        self::assertSame(
            [],
            $this->inspect(UnsetHiddenArticle::class)->hiddenColumns(),
        );
        self::assertSame(
            [],
            $this->inspect(StringHiddenArticle::class)->hiddenColumns(),
        );
        self::assertSame(
            ['secret'],
            $this->inspect(MixedHiddenArticle::class)->hiddenColumns(),
        );
    }

    /**
     * @param class-string $model
     */
    private function inspect(string $model): ModelInspector
    {
        return new ModelInspector(self::createReflectionProvider(), $model);
    }
}
