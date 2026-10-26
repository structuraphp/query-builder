<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleController;
use Tests\StructuraPhp\QueryBuilder\Fixtures\TestQueryRequest;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('authors', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
        });

        Schema::create('articles', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->integer('views')->default(0);
            $table->float('rating')->default(0);
            $table->boolean('featured')->default(false);
            $table->date('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id');
            $table->string('body');
        });
    }

    protected function defineEnvironment($app): void
    {
        $config = $app->make(Repository::class);
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineRoutes($router): void
    {
        $controller = ArticleController::class;

        Route::get('/articles', [$controller, 'index']);
        Route::get('/articles-override', [$controller, 'override']);
        Route::get('/articles-data', [$controller, 'data']);
        Route::get('/articles-precog', [$controller, 'index'])
            ->middleware(HandlePrecognitiveRequests::class);
    }

    /**
     * @param TestResponse<Response> $response
     *
     * @return list<mixed>
     */
    protected static function column(
        TestResponse $response,
        string $column,
        string $key = 'data',
    ): array {
        $data = $response->json($key);
        self::assertIsArray($data);

        return array_column($data, $column);
    }

    /**
     * Error keys contain dots, so they cannot go through
     * `json('errors.key')`.
     *
     * @param TestResponse<Response> $response
     */
    protected static function errorMessage(
        TestResponse $response,
        string $key,
    ): string {
        $errors = $response->json('errors');
        self::assertIsArray($errors);
        $messages = $errors[$key] ?? null;
        self::assertIsArray($messages);
        $message = $messages[0] ?? null;
        self::assertIsString($message);

        return $message;
    }

    /**
     * @param class-string<FromModel> $query
     * @param array<string, mixed> $params
     *
     * @return TestResponse<Response>
     */
    protected function fetch(string $query, array $params = []): TestResponse
    {
        TestQueryRequest::$queryClass = $query;

        return $this->getJson('/articles?' . http_build_query($params));
    }

    /**
     * Follows `next_cursor` until the last page and returns every id, in order.
     *
     * @param class-string<FromModel> $query
     * @param array<string, mixed> $params
     *
     * @return list<mixed>
     */
    protected function walkCursor(string $query, array $params): array
    {
        $ids = [];
        $cursor = null;
        do {
            $response = $this->fetch(
                $query,
                $params + ($cursor !== null ? ['cursor' => $cursor] : []),
            );
            $data = $response->json('data');
            $ids = [
                ...$ids,
                ...array_column(\is_array($data) ? $data : [], 'id'),
            ];
            $cursor = $response->json('next_cursor');
        } while ($cursor !== null);

        return $ids;
    }
}
