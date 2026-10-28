<div align="center" style="display:grid;place-items:center;">
    <h1>Query Builder</h1>
    <p>Turn HTTP query strings into safe, declarative Eloquent queries.</p>

[![License](https://img.shields.io/github/license/structuraphp/query-builder.svg)](https://github.com/structuraphp/query-builder/blob/main/LICENSE "LICENSE")
[![PHP from Packagist](https://img.shields.io/badge/PHP-%3E%3D8.4-%238892bf)](#requirements "PHP version 8.4 minimum")
[![Laravel](https://img.shields.io/badge/Laravel-13-%23ff2d20)](#requirements "Laravel 13")
[![Packagist Downloads](https://img.shields.io/packagist/dm/structuraphp/query-builder)](https://packagist.org/packages/structuraphp/query-builder "packagist downloads")

**English** · [Français](README.fr.md)
</div>

## About

Your API lists need filters, sorting, relations and pagination. Writing them
by hand in every controller is repetitive; letting the client drive the query
is risky. Query Builder gives you both: the client composes its query from
the URL, and **you declare exactly what it may do**, in one class.

```sql
-- GET /articles?title[value]=laravel&title[mode]=like&popular=1&sort[date]=desc&with=author
SELECT * FROM articles
WHERE articles.title LIKE '%laravel%' ESCAPE '!' AND views >= 100
ORDER BY articles.created_at DESC LIMIT 25 OFFSET 0
SELECT id, name FROM authors WHERE authors.id IN (…)
```

Anything not declared is rejected: the client can never filter, sort or load
a sensitive column (`password`). Invalid input is a Laravel validation error
(422), never a 500.

## Requirements

| PHP Version  | Query Builder 0.x |
|--------------|-------------------|
| <= 8.3       | ✗ Unsupported     |
| 8.4 / 8.5    | ✓ Supported       |

- Laravel 13
- MySQL, PostgreSQL or SQLite

## Quick Start

### Installation

```shell
composer require structuraphp/query-builder
```

### 1. Declare the query

```php
final class ArticleQuery implements
    FromModel,
    WithFilter,
    WithScope,
    WithSort,
    WithRelation,
    WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('title')->type(Type::String);
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');               // Article::scopePopular()
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->default('date', Direction::Desc);
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author')->fields(['id', 'name']);
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(25)->maxLimit(100);
    }
}
```

### 2. Create the request

```php
final class ArticleQueryRequest extends QueryRequest
{
    protected function getQuery(): string
    {
        return ArticleQuery::class;
    }
}
```

The validation rules are generated from the declaration.

### 3. Use it in a controller

```php
public function index(ArticleQueryRequest $request)
{
    $parameters = $request->parameters();
    $query = $parameters->builder();    // an Eloquent Builder, still yours
    $query->where('tenant_id', $request->user()->tenant_id);

    return $parameters->paginate($query);
}
```

That's it: the request above returns a paginated, filtered and sorted list of
articles, with their authors.

## Features

- **🔒 Declared, never guessed.** The client only uses what the query class
  declares. Public names stay apart from the schema (`column()`), and the
  loaded columns of a relation are yours (`fields()`).
- **✅ Laravel validation.** Rules, messages and attributes are generated:
  the client gets field-level 422 errors. Add your own rules and messages,
  and validate without HTTP too (`QueryParameters::fromArray()`).
- **🔎 Typed filters.** String, integer, float, boolean and date filters with
  their operators; `like` searches escaped and case-insensitive; date
  comparisons by day that keep your indexes usable.
- **🧩 Scopes with real arguments.** Call model scopes, `#[Scope]` methods or
  methods of your custom builder. Arguments are validated and converted from
  the PHP signature: enums, dates, arrays, nested structures, or your own
  objects through a cast.
- **🔐 Server-side context.** Give scope arguments from the code (the
  current user, ids computed by a service), never from the client, and
  compute them only when the scope is requested.
- **↕️ Sorting.** Declared sorts on columns or relation counts, priority
  from the URL, default sorts.
- **🔗 Relations.** The client picks the relations to eager load or count,
  among the declared ones.
- **📄 Pagination.** Offset, cursor (stable, without count query) or none,
  with a limit the client cannot exceed.
- **🧱 Domain-friendly.** `QueryParameters` is a value object without HTTP
  dependency: pass it to a service, keep refining the builder.
- **📘 OpenAPI ready.** [Scramble](https://scramble.dedoc.co) documents your
  query requests like any form request.

## 📖 Documentation

The [usage guide](docs/README.md) details every feature with its
configuration, example URLs, and the SQL or validation error they produce:
[filters](docs/filters.md), [scopes](docs/scopes.md),
[context](docs/context.md), [sorting](docs/sorting.md),
[relations](docs/relations.md) and [pagination](docs/pagination.md).
The [PHPStan rules](docs/phpstan.md) report configuration errors in CI.

## 🤖 AI agents

With [Laravel Boost](https://laravel.com/docs/13.x/boost),
`php artisan boost:install` gives your AI agents the library's guidelines and
its `structura-query-builder` skill.

## License

Query Builder is open-source software licensed under the
[MIT license](LICENSE).
