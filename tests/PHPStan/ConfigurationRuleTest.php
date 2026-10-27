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
