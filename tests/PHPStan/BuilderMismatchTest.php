<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Illuminate\Database\Eloquent\Builder;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\PHPStan\ConfigurationCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryClassCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryConfigurationRule;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ArticleBuilder;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;

/**
 * @extends RuleTestCase<QueryConfigurationRule>
 */
final class BuilderMismatchTest extends RuleTestCase
{
    private const string DATA = 'Tests\StructuraPhp\QueryBuilder\PHPStan\Data';

    public function testDeclaredBuilderMustBeTheModelOne(): void
    {
        $declared = ArticleBuilder::class . '<' . Article::class . '>';

        $this->analyse([__DIR__ . '/data/builder-declared.php'], [
            [
                $this->message(
                    self::DATA . '\OtherBuilderQuery',
                    $declared,
                    Article::class,
                    Builder::class,
                ),
                49,
            ],
            [
                $this->message(
                    self::DATA . '\OtherModelQuery',
                    $declared,
                    BuiltArticle::class,
                    ArticleBuilder::class,
                ),
                60,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new QueryConfigurationRule($this->createReflectionProvider());
    }

    #[Override]
    protected function getCollectors(): array
    {
        return [new QueryClassCollector(), new ConfigurationCollector()];
    }

    private function message(
        string $query,
        string $declared,
        string $model,
        string $builder,
    ): string {
        return \sprintf(
            'Query %s declares the builder %s, but %s builds a %s.',
            $query,
            $declared,
            $model,
            $builder,
        );
    }
}
