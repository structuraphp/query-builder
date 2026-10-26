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
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\Service\Definition;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
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
        yield 'unknown default' => [function (PaginationInterface $p): void {
            $p->default('page');
        }, 'Unknown pagination mode "page".'];

        yield 'unknown allowed' => [function (PaginationInterface $p): void {
            $p->allow('offset', 'page');
        }, 'Unknown pagination mode "page".'];

        yield 'refused default' => [function (PaginationInterface $p): void {
            $p->allow('offset')->default('cursor');
        }, 'Default pagination mode "cursor" is not allowed.'];

        yield 'limit below 1' => [function (PaginationInterface $p): void {
            $p->limit(0);
        }, 'Pagination limit must be between 1 and 100.'];

        yield 'limit above max' => [function (PaginationInterface $p): void {
            $p->limit(101);
        }, 'Pagination limit must be between 1 and 100.'];
    }

    #[DataProvider('limitBounds')]
    public function testLimitBoundsAreAllowed(int $limit): void
    {
        $query = new ConfigurableQuery(
            pagination: function (PaginationInterface $p) use ($limit): void {
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
        yield 'reserved name' => [function (FilterInterface $f): void {
            $f->filter('page')->type(Type::String);
        }];

        yield 'duplicate' => [function (FilterInterface $f): void {
            $f->filter('title')->type(Type::String);
            $f->filter('title')->type(Type::String);
        }];

        yield 'missing type' => [function (FilterInterface $f): void {
            $f->filter('title');
        }];

        yield 'dot in name' => [function (FilterInterface $f): void {
            $f->filter('a.b')->type(Type::String);
        }];

        yield 'star in name' => [function (FilterInterface $f): void {
            $f->filter('a*')->type(Type::String);
        }];
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
        yield 'undeclared default' => [function (SortInterface $s): void {
            $s->default('title');
        }];

        yield 'bad direction' => [function (SortInterface $s): void {
            $s->sort('title');
            $s->default('title', 'up');
        }];

        yield 'duplicate' => [function (SortInterface $s): void {
            $s->sort('title');
            $s->sort('title');
        }];

        yield 'dot in name' => [function (SortInterface $s): void {
            $s->sort('a.b');
        }];

        yield 'star in name' => [function (SortInterface $s): void {
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
            filter: function (FilterInterface $filter): void {
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
        yield 'same name as filter' => [function (ScopeInterface $s): void {
            $s->scope('title')->method('popular');
        }];

        yield 'reserved name' => [function (ScopeInterface $s): void {
            $s->scope('limit')->method('popular');
        }];

        yield 'duplicate' => [function (ScopeInterface $s): void {
            $s->scope('hot')->method('popular');
            $s->scope('hot')->method('popular');
        }];

        yield 'dot in name' => [function (ScopeInterface $s): void {
            $s->scope('a.b')->method('popular');
        }];

        yield 'star in name' => [function (ScopeInterface $s): void {
            $s->scope('a*')->method('popular');
        }];
    }

    #[DataProvider('invalidRelations')]
    public function testInvalidRelationConfigThrows(string $name): void
    {
        $this->expectException(ConfigurationException::class);
        $query = new ConfigurableQuery(
            relation: function (RelationInterface $relation) use ($name): void {
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

        yield 'bad nested segment' => ['author.comments:id'];
    }

    public function testCountMustBeARootRelation(): void
    {
        $this->expectExceptionObject(new ConfigurationException(
            '"author.comments" is not a valid name.',
        ));
        $query = new ConfigurableQuery(
            relation: function (RelationInterface $relation): void {
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
            pagination: function (PaginationInterface $p): void {
                $p->default('cursor');
            },
            sort: function (SortInterface $s): void {
                $s->sort('popularity')->count('comments');
                $s->default('popularity');
            },
        );

        Definition::fromQuery($query);
    }
}
