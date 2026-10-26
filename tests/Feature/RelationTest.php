<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Feature;

use Illuminate\Support\Facades\DB;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\PlainArticleQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\RelationArticleQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class RelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('authors')->insert([
            'id' => 1,
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret',
        ]);
        DB::table('articles')->insert([
            'id' => 1,
            'author_id' => 1,
            'title' => 'Foo',
        ]);
        DB::table('comments')->insert([
            ['article_id' => 1, 'body' => 'First'],
            ['article_id' => 1, 'body' => 'Second'],
        ]);
    }

    public function testLoadsDeclaredRelations(): void
    {
        $response = $this->fetch(RelationArticleQuery::class, [
            'with' => 'author,comments',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.author.name', 'Ada')
            ->assertJsonCount(2, 'data.0.comments');
    }

    public function testFieldsLimitColumns(): void
    {
        $response = $this->fetch(RelationArticleQuery::class, [
            'with' => 'author',
        ]);

        $response->assertOk();
        self::assertSame(
            ['id', 'name'],
            array_keys((array) $response->json('data.0.author')),
        );
    }

    public function testNestedRelationKeepsTheFieldsOfItsParent(): void
    {
        $response = $this->fetch(RelationArticleQuery::class, [
            'with' => 'author.articles',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.author.articles.0.title', 'Foo');
        self::assertSame(
            ['id', 'name', 'articles'],
            array_keys((array) $response->json('data.0.author')),
        );
    }

    public function testSpacesAndEmptyItems(): void
    {
        $response = $this->fetch(RelationArticleQuery::class, [
            'with' => ' author , ,comments ',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.author.name', 'Ada')
            ->assertJsonCount(2, 'data.0.comments');
    }

    public function testUndeclaredRelationIs422(): void
    {
        $response = $this->fetch(RelationArticleQuery::class, [
            'with' => 'author,secret',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('with');
    }

    public function testWithWithoutWithRelationIs422(): void
    {
        $response = $this->fetch(PlainArticleQuery::class, [
            'with' => 'author',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('with');
    }
}
