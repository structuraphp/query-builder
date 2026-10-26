<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\Service\Definition;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\SecretViewsArticle;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class DefinitionTest extends TestCase
{
    /**
     * @param Closure(PaginationInterface): void $configure
     */
    #[DataProvider('invalidPagination')]
    public function testInvalidPaginationConfigThrows(
        Closure $configure,
        string $message,
    ): void {
        $this->expectExceptionObject(new ConfigurationException($message));
        $query = new ConfigurableQuery(pagination: $configure);

        Definition::fromQuery($query);
    }

    /**
     * @return iterable<string, array{
     *     Closure(PaginationInterface): void,
     *     string,
     * }>
     */
    public static function invalidPagination(): iterable
    {
        yield 'refused default' => [static function (
            PaginationInterface $p,
        ): void {
            $p->allow(PaginationMode::Offset)->default(PaginationMode::Cursor);
        }, 'Default pagination mode "cursor" is not allowed.'];

        yield 'limit below 1' => [static function (
            PaginationInterface $p,
        ): void {
            $p->limit(0);
        }, 'Pagination limit must be between 1 and 100.'];

        yield 'limit above max' => [static function (
            PaginationInterface $p,
        ): void {
            $p->limit(101);
        }, 'Pagination limit must be between 1 and 100.'];
    }

    #[DataProvider('limitBounds')]
    public function testLimitBoundsAreAllowed(int $limit): void
    {
        $query = new ConfigurableQuery(
            pagination: static function (
                PaginationInterface $p,
            ) use ($limit): void {
                $p->limit($limit);
            },
        );

        $definition = Definition::fromQuery($query);

        self::assertSame($limit, $definition->pagination->limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function limitBounds(): iterable
    {
        yield 'one' => [1];

        yield 'max' => [100];
    }

    /**
     * @param Closure(FilterInterface): void $configure
     */
    #[DataProvider('invalidFilters')]
    public function testInvalidFilterConfigThrows(Closure $configure): void
    {
        $this->expectException(ConfigurationException::class);
        $query = new ConfigurableQuery(filter: $configure);

        Definition::fromQuery($query);
    }

    /**
     * @return iterable<string, array{Closure(FilterInterface): void}>
     */
    public static function invalidFilters(): iterable
    {
        yield 'reserved name' => [static function (FilterInterface $f): void {
            $f->filter('page')->type(Type::String);
        }];

        yield 'duplicate' => [static function (FilterInterface $f): void {
            $f->filter('title')->type(Type::String);
            $f->filter('title')->type(Type::String);
        }];

        yield 'missing type' => [static function (FilterInterface $f): void {
            $f->filter('title');
        }];

        yield 'dot in name' => [static function (FilterInterface $f): void {
            $f->filter('a.b')->type(Type::String);
        }];

        yield 'star in name' => [static function (FilterInterface $f): void {
            $f->filter('a*')->type(Type::String);
        }];

        yield 'empty string' => [static function (FilterInterface $f): void {
            $f->filter('title')->type(Type::String)->maxLength(0);
        }];

        yield 'no item in in mode' => [static function (
            FilterInterface $f,
        ): void {
            $f->filter('views')->type(Type::Integer)->maxItems(0);
        }];
    }

    public function testOneCharacterIsEnough(): void
    {
        $query = new ConfigurableQuery(
            filter: static function (FilterInterface $f): void {
                $f->filter('title')->type(Type::String)->maxLength(1);
            },
        );

        $definition = Definition::fromQuery($query);

        self::assertSame(1, $definition->filters['title']->maxLength);
    }

    public function testOneItemInInModeIsEnough(): void
    {
        $query = new ConfigurableQuery(
            filter: static function (FilterInterface $f): void {
                $f->filter('views')->type(Type::Integer)->maxItems(1);
            },
        );

        $definition = Definition::fromQuery($query);

        self::assertSame(1, $definition->filters['views']->maxItems);
    }

    /**
     * @param Closure(SortInterface): void $configure
     */
    #[DataProvider('invalidSorts')]
    public function testInvalidSortConfigThrows(Closure $configure): void
    {
        $this->expectException(ConfigurationException::class);
        $query = new ConfigurableQuery(sort: $configure);

        Definition::fromQuery($query);
    }

    /**
     * @return iterable<string, array{Closure(SortInterface): void}>
     */
    public static function invalidSorts(): iterable
    {
        yield 'undeclared default' => [static function (
            SortInterface $s,
        ): void {
            $s->default('title');
        }];

        yield 'duplicate' => [static function (SortInterface $s): void {
            $s->sort('title');
            $s->sort('title');
        }];

        yield 'dot in name' => [static function (SortInterface $s): void {
            $s->sort('a.b');
        }];

        yield 'star in name' => [static function (SortInterface $s): void {
            $s->sort('a*');
        }];
    }

    /**
     * @param Closure(ScopeInterface): void $configure
     */
    #[DataProvider('invalidScopes')]
    public function testInvalidScopeConfigThrows(Closure $configure): void
    {
        $this->expectException(ConfigurationException::class);
        $query = new ConfigurableQuery(
            filter: static function (FilterInterface $filter): void {
                $filter->filter('title')->type(Type::String);
            },
            scope: $configure,
        );

        Definition::fromQuery($query);
    }

    /**
     * @return iterable<string, array{Closure(ScopeInterface): void}>
     */
    public static function invalidScopes(): iterable
    {
        yield 'same name as filter' => [static function (
            ScopeInterface $s,
        ): void {
            $s->scope('title')->method('popular');
        }];

        yield 'reserved name' => [static function (ScopeInterface $s): void {
            $s->scope('limit')->method('popular');
        }];

        yield 'duplicate' => [static function (ScopeInterface $s): void {
            $s->scope('hot')->method('popular');
            $s->scope('hot')->method('popular');
        }];

        yield 'dot in name' => [static function (ScopeInterface $s): void {
            $s->scope('a.b')->method('popular');
        }];

        yield 'star in name' => [static function (ScopeInterface $s): void {
            $s->scope('a*')->method('popular');
        }];
    }

    #[DataProvider('invalidRelations')]
    public function testInvalidRelationConfigThrows(string $name): void
    {
        $this->expectException(ConfigurationException::class);
        $query = new ConfigurableQuery(
            relation: static function (
                RelationInterface $relation,
            ) use ($name): void {
                $relation->relation('author');
                $relation->relation($name);
            },
        );

        Definition::fromQuery($query);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRelations(): iterable
    {
        yield 'unknown relation' => ['secret'];

        yield 'unknown nested root' => ['secret.author'];

        yield 'comma' => ['author,comments'];

        yield 'colon' => ['author:id'];

        yield 'bad nested segment' => ['author.articles:id'];
    }

    public function testNestedRelationNeedsItsParentDeclared(): void
    {
        $this->expectExceptionObject(new ConfigurationException(
            'Relation "author.articles" needs its parent "author" declared.',
        ));
        $query = new ConfigurableQuery(
            relation: static function (RelationInterface $relation): void {
                $relation->relation('comments');
                $relation->relation('author.articles');
            },
        );

        Definition::fromQuery($query);
    }

    public function testCountMustBeARootRelation(): void
    {
        $this->expectExceptionObject(new ConfigurationException(
            '"author.comments" is not a valid name.',
        ));
        $query = new ConfigurableQuery(
            relation: static function (RelationInterface $relation): void {
                $relation->count('author.comments');
            },
        );

        Definition::fromQuery($query);
    }

    public function testDefaultCountSortRefusesDefaultCursor(): void
    {
        $this->expectExceptionObject(new ConfigurationException(
            'Default sort "popularity" counts a relation: it cannot be used'
            . ' with the default cursor pagination.',
        ));
        $query = new ConfigurableQuery(
            pagination: static function (PaginationInterface $p): void {
                $p->default(PaginationMode::Cursor);
            },
            sort: static function (SortInterface $s): void {
                $s->sort('popularity')->count('comments');
                $s->default('popularity');
            },
        );

        Definition::fromQuery($query);
    }

    /**
     * @param Closure(SortInterface): void $sort
     */
    #[DataProvider('hiddenSorts')]
    public function testSortOnAHiddenColumnRefusesCursor(
        Closure $sort,
        string $message,
    ): void {
        $this->expectExceptionObject(new ConfigurationException($message));
        $query = new ConfigurableQuery(
            sort: $sort,
            model: SecretViewsArticle::class,
        );

        Definition::fromQuery($query);
    }

    /**
     * @return iterable<string, array{Closure(SortInterface): void, string}>
     */
    public static function hiddenSorts(): iterable
    {
        yield 'by name' => [
            static function (SortInterface $s): void {
                $s->sort('title');
                $s->sort('views');
            },
            'Sort "views" orders on the hidden column "views": cursor'
            . ' pagination would expose its values.',
        ];

        yield 'by column' => [
            static function (SortInterface $s): void {
                $s->sort('popularity')->column('views');
            },
            'Sort "popularity" orders on the hidden column "views": cursor'
            . ' pagination would expose its values.',
        ];
    }

    public function testSortOnAHiddenColumnWithoutCursor(): void
    {
        $query = new ConfigurableQuery(
            pagination: static function (PaginationInterface $p): void {
                $p->allow(PaginationMode::Offset);
            },
            sort: static function (SortInterface $s): void {
                $s->sort('views');
            },
            model: SecretViewsArticle::class,
        );

        $definition = Definition::fromQuery($query);

        self::assertArrayHasKey('views', $definition->sorts);
    }
}
