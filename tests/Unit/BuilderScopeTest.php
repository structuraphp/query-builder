<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\OverriddenBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class BuilderScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['title' => 'Foo', 'views' => 5],
            ['title' => 'Bar', 'views' => 50],
        ]);
    }

    public function testBuilderMethodFromNewEloquentBuilder(): void
    {
        $titles = $this->titles(
            OverriddenBuilderArticle::class,
            'viewsAtLeast',
            ['viewsAtLeast' => ['min' => '10']],
        );

        self::assertSame(['Bar'], $titles);
    }

    public function testBuilderMethodWinsOverAModelScope(): void
    {
        $titles = $this->titles(
            BuiltArticle::class,
            'viewsAtLeast',
            ['viewsAtLeast' => ['min' => '10']],
        );

        self::assertSame(['Bar'], $titles);
    }

    public function testBuilderMethodArgumentsAreValidated(): void
    {
        $this->expectException(ValidationException::class);

        $this->titles(
            BuiltArticle::class,
            'viewsAtLeast',
            ['viewsAtLeast' => ['min' => 'foo']],
        );
    }

    public function testBaseBuilderMethodIsNotAScope(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->titles(BuiltArticle::class, 'delete', []);
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, mixed> $input
     *
     * @return list<mixed>
     */
    private function titles(string $model, string $name, array $input): array
    {
        $query = new ConfigurableQuery(
            scope: function (ScopeInterface $scope) use ($name): void {
                $scope->scope($name);
            },
            model: $model,
        );

        $builder = QueryParameters::fromArray($query, $input)->builder();
        $builder->getQuery()->orderBy('id');

        return array_values($builder->pluck('title')->all());
    }
}
