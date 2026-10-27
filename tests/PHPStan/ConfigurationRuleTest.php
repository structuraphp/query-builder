<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\PHPStan\ConfigurationCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryClassCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryConfigurationRule;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;

/**
 * @extends RuleTestCase<QueryConfigurationRule>
 */
final class ConfigurationRuleTest extends RuleTestCase
{
    public function testInvalidName(): void
    {
        $this->analyse([__DIR__ . '/data/config-names.php'], [
            ['"a.b" is not a valid name.', 24],
            ['"page" is a reserved query parameter.', 25],
            ['"title" is declared twice.', 27],
            ['"with.dot" is not a valid name.', 28],
            ['"title" is declared twice.', 33],
            ['"limit" is a reserved query parameter.', 35],
        ]);
    }

    public function testFilters(): void
    {
        $this->analyse([__DIR__ . '/data/config-filters.php'], [
            ['Filter "untyped" has no type.', 22],
            [
                'Filter "items" must accept at least one item in in mode.',
                25,
            ],
            [
                'Filter "length" must accept at least one character.',
                26,
            ],
            ['Filter "both" has no type.', 27],
            [
                'Filter "named" must accept at least one character.',
                30,
            ],
        ]);
    }

    public function testSorts(): void
    {
        $this->analyse([__DIR__ . '/data/config-sorts.php'], [
            ['"a-b" is not a valid name.', 25],
            ['Sort "title" is declared twice.', 27],
            [
                'Sort "both" cannot have both column() and count().',
                28,
            ],
            [$this->missing('nothing'), 29],
            ['"comments.author" is not a valid name.', 30],
            ['Default sort "missing" is not declared.', 33],
            ['Sort "title" is declared twice.', 50],
            [
                'Default sort "comments" counts a relation: it cannot be'
                . ' used with the default cursor pagination.',
                52,
            ],
        ]);
    }

    public function testRelations(): void
    {
        $this->analyse([__DIR__ . '/data/config-relations.php'], [
            [$this->missing('ghost'), 21],
            [$this->missing('author.bad name'), 22],
            ['Relation "comments" is declared twice.', 24],
            [
                'Relation "author.posts" needs its parent "author"'
                . ' declared.',
                27,
            ],
            ['Relation "author.posts" is declared twice.', 28],
            ['Count "comments" is declared twice.', 30],
            ['"comments.author" is not a valid name.', 31],
            ['"comments.author" is not a valid name.', 32],
            [$this->missing('ghost'), 33],
        ]);
    }

    public function testConstrainedCountIsStillChecked(): void
    {
        $this->analyse(
            [__DIR__ . '/data/config-relation-constraints.php'],
            [
                [$this->missing('ghost'), 23],
                ['Count "comments" is declared twice.', 25],
            ],
        );
    }

    public function testPagination(): void
    {
        $this->analyse([__DIR__ . '/data/config-pagination.php'], [
            [$this->mode('none'), 25],
            [$this->mode('offset'), 33],
            [$this->mode('none'), 54],
            [$this->mode('none'), 62],
            [$this->limit(100), 88],
            [$this->limit(100), 98],
            [$this->limit(100), 106],
            [$this->limit(10), 115],
            [$this->limit(49), 131],
            [$this->limit(0), 139],
            [$this->limit(100), 155],
        ]);
    }

    public function testOptionOnUnknownArgument(): void
    {
        $this->analyse([__DIR__ . '/data/config-scope-options.php'], [
            [$this->noArgument('c1', 'foo'), 22],
            [$this->noArgument('c2', 'foo'), 23],
            [$this->noArgument('c3', 'foo'), 24],
            [$this->noArgument('c4', 'foo'), 25],
            [$this->noArgument('c5', 'foo'), 26],
            [$this->noArgument('c6', 'foo'), 27],
            [$this->noArgument('c7', 'foo'), 28],
            [$this->noArgument('c8', 'foo'), 29],
            [$this->noArgument('c9', 'foo'), 30],
            [$this->noArgument('c10', 'yyy'), 36],
            [$this->noArgument('c11', 'aaa'), 41],
            [$this->noArgument('c13', 'mmm'), 45],
            [$this->noArgument('c12', 'bar'), 48],
            [$this->named('p1'), 51],
            [$this->named('p2'), 52],
            [$this->named('p3'), 54],
            [$this->named('p4'), 55],
            [$this->named('p5'), 60],
            [$this->noArgument('v2', 'zzz'), 68],
        ]);
    }

    public function testOptionOnContextArgument(): void
    {
        $this->analyse([__DIR__ . '/data/config-scope-context.php'], [
            [$this->context('x1', 'status', 'cast'), 24],
            [$this->context('x2', 'status', 'rules'), 26],
            [$this->context('x3', 'status', 'each'), 28],
            [$this->context('x4', 'status', 'nested'), 30],
            [$this->context('x5', 'status', 'messages'), 32],
            [$this->context('x6', 'status', 'attribute'), 34],
            [$this->context('x7', 'status', 'attribute'), 36],
            [$this->context('x8', 'status', 'cast'), 42],
            [$this->context('x9', 'author', 'rules'), 47],
            [$this->context('x10', 'status', 'cast'), 52],
            [$this->needsCast('x11', 'author'), 53],
        ]);
    }

    public function testUntypedArgumentWithoutCast(): void
    {
        $this->analyse([__DIR__ . '/data/config-scope-types.php'], [
            [$this->needsCast('badEnum', 'a'), 124],
            [$this->needsCast('unit', 'a'), 125],
            [$this->needsCast('plainObject', 'a'), 126],
            [$this->needsCast('loose', 'a'), 127],
            [$this->needsCast('unionDate', 'a'), 129],
            [$this->needsCast('both', 'b'), 130],
            [$this->needsCast('scalars', 'b'), 132],
        ]);
    }

    public function testInvalidCast(): void
    {
        $this->analyse([__DIR__ . '/data/config-scope-casts.php'], [
            [$this->cast('k1', 'lng'), 31],
            [$this->cast('k4', 'lng'), 42],
            [$this->cast('k9', 'status'), 53],
            [$this->cast('k12', 'lng'), 65],
            [$this->noArgument('o1', 'foo'), 73],
            [$this->noArgument('m1', 'lat'), 77],
        ]);
    }

    public function testHiddenSortWithCursor(): void
    {
        $this->analyse([__DIR__ . '/data/config-scope-hidden.php'], [
            [$this->hidden('views', 'views'), 29],
            [$this->hidden('popularity', 'views'), 31],
            [$this->hidden('title', 'title'), 50],
            [$this->hidden('views', 'views'), 94],
        ]);
    }

    public function testValidConfigurationHasNoError(): void
    {
        $this->analyse([__DIR__ . '/data/config-valid.php'], []);
    }

    public function testUnreadableValueSkipsItsCheck(): void
    {
        $this->analyse([__DIR__ . '/data/config-unreadable.php'], []);
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new QueryConfigurationRule(self::createReflectionProvider());
    }

    #[Override]
    protected function getCollectors(): array
    {
        return [new QueryClassCollector(), new ConfigurationCollector()];
    }

    private function missing(string $name): string
    {
        return \sprintf(
            'Relation "%s" does not exist on %s.',
            $name,
            Article::class,
        );
    }

    private function noArgument(string $scope, string $name): string
    {
        return \sprintf('Scope "%s" has no argument "%s".', $scope, $name);
    }

    private function named(string $scope): string
    {
        return \sprintf('Scope "%s" options must use named arguments.', $scope);
    }

    private function context(
        string $scope,
        string $argument,
        string $option,
    ): string {
        return \sprintf(
            'Scope "%s" argument "%s" comes from the context: it takes no'
            . ' %s().',
            $scope,
            $argument,
            $option,
        );
    }

    private function needsCast(string $scope, string $argument): string
    {
        return \sprintf(
            'Scope "%s" argument "%s" needs a scalar, enum, date or array'
            . ' type, or an explicit cast().',
            $scope,
            $argument,
        );
    }

    private function cast(string $scope, string $argument): string
    {
        return \sprintf(
            'Scope "%s" argument "%s" cast must be a Type, a backed enum'
            . ' or a closure.',
            $scope,
            $argument,
        );
    }

    private function hidden(string $sort, string $column): string
    {
        return \sprintf(
            'Sort "%s" orders on the hidden column "%s": cursor pagination'
            . ' would expose its values.',
            $sort,
            $column,
        );
    }

    private function mode(string $mode): string
    {
        return \sprintf(
            'Default pagination mode "%s" is not allowed.',
            $mode,
        );
    }

    private function limit(int $max): string
    {
        return \sprintf('Pagination limit must be between 1 and %d.', $max);
    }
}
