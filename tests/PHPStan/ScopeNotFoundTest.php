<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Override;
use PHPStan\Analyser\CollectedDataEmitter;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\NodeCallbackInvoker;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\PHPStan\ConfigurationCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryClassCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryConfigurationRule;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\BuiltArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\FinalBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\InheritedBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\InheritedPropertyArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\MissingAttributeArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\NonBuilderAttributeArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\PrivateScopeArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\PropertyBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\TraitBuilderArticle;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\TraitScopeArticle;

/**
 * @extends RuleTestCase<QueryConfigurationRule>
 */
final class ScopeNotFoundTest extends RuleTestCase
{
    public function testUnknownScopeIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/scope-not-found.php'], [
            [$this->message('missing', PrivateScopeArticle::class), 37],
            [$this->message('author', PrivateScopeArticle::class), 41],
            [$this->message('hush', PrivateScopeArticle::class), 43],
        ]);
    }

    public function testAliasIsLookedUp(): void
    {
        $this->analyse([__DIR__ . '/data/scope-alias.php'], [
            [$this->message('nope', Article::class), 23],
            [$this->message('later', Article::class), 26],
        ]);
    }

    public function testBuilderMethodsAreScopes(): void
    {
        $this->analyse([__DIR__ . '/data/scope-builder.php'], [
            [$this->message('delete', BuiltArticle::class), 79],
            [$this->message('where', BuiltArticle::class), 80],
            [$this->message('missing', InheritedBuilderArticle::class), 94],
            [$this->message('missing', PropertyBuilderArticle::class), 108],
            [$this->message('shared', TraitBuilderArticle::class), 162],
            [$this->message('guarded', TraitBuilderArticle::class), 163],
        ]);
    }

    public function testBuilderComesFromWhereLaravelReadsIt(): void
    {
        $this->analyse([__DIR__ . '/data/scope-builder-sources.php'], [
            [$this->message('missing', FinalBuilderArticle::class), 147],
            [
                $this->message('missing', NonBuilderAttributeArticle::class),
                161,
            ],
            [$this->message('missing', MissingAttributeArticle::class), 175],
            [$this->message('missing', InheritedPropertyArticle::class), 215],
            [$this->message('missing', TraitScopeArticle::class), 229],
        ]);
    }

    public function testInheritedConfigurationIsCheckedPerChild(): void
    {
        $parent = __DIR__ . '/data/scope-inherited-parent.php';

        $errors = $this->gatherAnalyserErrors([
            $parent,
            __DIR__ . '/data/scope-inherited.php',
        ]);

        self::assertCount(1, $errors);
        self::assertSame(
            $this->message('popular', BuiltArticle::class),
            $errors[0]->getMessage(),
        );
        self::assertSame($parent, $errors[0]->getFile());
        self::assertSame(15, $errors[0]->getLine());
        self::assertSame(
            'structuraQueryBuilder.scopeNotFound',
            $errors[0]->getIdentifier(),
        );
    }

    public function testNothingIsReportedOnPartialAnalysis(): void
    {
        $file = __DIR__ . '/data/scope-not-found.php';
        $data = [
            $file => [
                QueryClassCollector::class => [
                    'Tests\StructuraPhp\QueryBuilder\PHPStan\Data'
                    . '\ScopeNotFoundQuery',
                ],
                ConfigurationCollector::class => [
                    [
                        'class' => 'Tests\StructuraPhp\QueryBuilder\PHPStan'
                            . '\Data\ScopeNotFoundQuery',
                        'method' => 'getmodel',
                        'file' => $file,
                        'model' => Article::class,
                    ],
                    [
                        'class' => 'Tests\StructuraPhp\QueryBuilder\PHPStan'
                            . '\Data\ScopeNotFoundQuery',
                        'method' => 'scope',
                        'file' => $file,
                        'calls' => [[
                            'target' => 0,
                            'result' => 1,
                            'method' => 'scope',
                            'line' => 37,
                            'args' => [
                                ['kind' => 'string', 'value' => 'missing'],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
        $rule = $this->getRule();
        $scope = self::createStubForIntersectionOfInterfaces([
            Scope::class,
            CollectedDataEmitter::class,
            DependencyTracker::class,
            NodeCallbackInvoker::class,
        ]);
        // @phpstan-ignore phpstanApi.constructor
        $full = new CollectedDataNode($data, false);
        // @phpstan-ignore phpstanApi.constructor
        $partial = new CollectedDataNode($data, true);

        // A list of errors, as Rule::processNode() returns.
        self::assertSame([0], array_keys($rule->processNode($full, $scope)));
        self::assertSame([], $rule->processNode($partial, $scope));
    }

    public function testNameWithSeveralValuesIsIgnored(): void
    {
        $this->analyse([__DIR__ . '/data/scope-several-values.php'], [
            [$this->message('gone', Article::class), 32],
            [$this->message('away', Article::class), 33],
        ]);
    }

    public function testAnonymousQueryIsSkipped(): void
    {
        $this->analyse([__DIR__ . '/data/scope-anonymous.php'], []);
    }

    public function testQueryWithUnreadableModelIsIgnored(): void
    {
        $this->analyse([__DIR__ . '/data/scope-unreadable-model.php'], []);
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

    private function message(string $scope, string $model): string
    {
        return \sprintf('Scope "%s" does not exist on %s.', $scope, $model);
    }
}
