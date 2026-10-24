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

Turns the query string of an HTTP request into an Eloquent query: filters,
scopes, sorting, relations and pagination.

Everything the client can do is **declared** in a query class. Anything not
declared is rejected: the client can never filter, sort or load a sensitive
column or relation (`password`, etc.). Invalid input yields a Laravel
validation error (422 in JSON), never a 500.

## Quick Start

### Installation

```shell
composer require structuraphp/query-builder
```

### 1. Declare the query

A query class implements `FromModel`, then one `With*` interface per feature
used. Each interface is optional.

```php
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use StructuraPhp\QueryBuilder\Enum\Type;

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
        $filter->filter('views')->type(Type::Integer);
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');
        $scope->scope('publishedBetween');
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->sort('title');
        $sort->default('date', 'desc');
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author')->fields(['id', 'name']);
        $relation->relation('comments');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default('cursor')->limit(25)->maxLimit(100);
    }
}
```

The class is instantiated by the Laravel container: it can receive
dependencies in its constructor.

### 2. Create the request

```php
use StructuraPhp\QueryBuilder\Service\QueryRequest;

final class ArticleQueryRequest extends QueryRequest
{
    protected function getQuery(): string
    {
        return ArticleQuery::class;
    }
}
```

Validation rules, messages and attributes are generated from the declaration.
The request's `messages()` and `attributes()` methods remain available and
take precedence over the config.

### 3. Use the parameters

The request only validates. `parameters()` returns a `QueryParameters`, a
value object with no HTTP dependency, which can be passed to a domain
service:

```php
// Controller
public function index(
    ArticleQueryRequest $request,
    ArticleService $service,
) {
    return $service->list($request->parameters());
}

// Domain service
public function list(QueryParameters $parameters)
{
    $query = $parameters->builder(); // filters, scopes, sorting and relations
    $query->where('tenant_id', $this->tenant->id);

    return $parameters->paginate($query);
}
```

`builder()` returns an Eloquent `Builder` that can still be modified.
`paginate()` returns a `LengthAwarePaginator` (`offset` mode), a
`CursorPaginator` (`cursor` mode) or a `Collection` (`none` mode).

### Without an HTTP request

From a command, a job or a test, `fromArray()` applies the same validation
and throws a `ValidationException` if the data is invalid:

```php
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;

$parameters = QueryParameters::fromArray(app(ArticleQuery::class), [
    'title' => ['value' => 'laravel', 'mode' => 'like'],
    'limit' => 20,
]);

$page = $parameters->paginate($parameters->builder());
```

## Requirements

| PHP Version  | Query Builder 0.x |
|--------------|-------------------|
| <= 8.3       | ✗ Unsupported     |
| 8.4 / 8.5    | ✓ Supported       |

- Laravel 13
- Supported engines: MySQL, PostgreSQL, SQLite

## URL format

### Filters

The filter name is at the root of the query string:

```
?title[value]=foo&title[mode]=like&title[not]=1
    → WHERE title NOT LIKE '%foo%'

?views[value]=100&views[mode]=>
    → WHERE views > 100
```

| Key     | Required    | Purpose                                   |
|---------|-------------|-------------------------------------------|
| `value` | yes         | Compared value                            |
| `mode`  | no (`=`)    | Operator, depending on the filter type    |
| `not`   | no          | Boolean: negates the condition            |

The filter type sets the value validation and the accepted modes:

| `Type`    | Validation          | Modes                       |
|-----------|---------------------|-----------------------------|
| `String`  | `string`            | `=`, `like`                 |
| `Integer` | `integer`           | `=`, `>`, `<`, `>=`, `<=`   |
| `Float`   | `numeric`           | `=`, `>`, `<`, `>=`, `<=`   |
| `Boolean` | `boolean`           | `=`                         |
| `Date`    | `date_format:Y-m-d` | `=`, `>`, `<`, `>=`, `<=`   |

- `like` wraps the value in `%…%` and matches `%`, `_` and `!` literally. It
  is case-insensitive (`ilike` on PostgreSQL; on MySQL, depending on the
  column collation).
- `Date` compares by day, even on a datetime column, without any SQL
  function on the column: `=` becomes `col >= day AND col < next day`, so
  the index remains usable.
- To exclude, add `not=1` (`?title[value]=foo&title[not]=1`): there is no
  `!=` mode.

### Scopes

Scopes are also at the root. Their arguments are named like the scope's PHP
parameters, in any order:

```
?popular=1
    → ->popular()

?publishedBetween[from]=2024-01-01&publishedBetween[to]=2024-12-31
    → ->publishedBetween(from: …, to: …)

?ofTags[tags][]=php&ofTags[tags][]=laravel
    → ->ofTags(['php', 'laravel'])
```

- A scope without arguments takes a boolean and is applied only if true.
- A scope missing from the URL is never applied.
- An argument missing from the URL takes the config default, then the PHP
  signature default. Without a default, it is required.

### Sorting

```
?sort[date]=desc&sort[title]=asc
    → ORDER BY created_at DESC, title ASC
```

Key order sets the priority. Without `sort`, the config's default sort
applies. As soon as the client sends `sort`, it replaces it entirely.

### Relations

```
?with=author,comments
```

Each relation must be declared. The loaded columns are set by the config
(`fields()`), never by the client.

### Pagination

| Parameter    | Default   | Purpose                                      |
|--------------|-----------|----------------------------------------------|
| `pagination` | `offset`  | Mode: `offset`, `cursor` or `none`           |
| `page`       | `1`       | Page number (`offset` mode)                  |
| `cursor`     | —         | Cursor (`cursor` mode)                       |
| `limit`      | `50`      | Items per page, capped by `maxLimit`         |

By default, only `offset` and `cursor` are allowed, with a `maxLimit` of 100.
The `none` mode must be explicitly allowed with `allow()`.

### Reserved keys

`with`, `sort`, `pagination`, `page`, `cursor` and `limit` cannot be used as
a filter or scope name. Other unknown keys (`utm_source`…) are ignored.

## Configuration

### Filters

```php
$filter->filter('statut')               // public name, in the URL
    ->column('status')                  // actual column (default: the name)
    ->type(Type::String)                // required
    ->rules(['in:draft,published'])     // Laravel rules on top of the type
    ->messages(['in' => 'Invalid status.'])
    ->attribute('statut');              // name shown in errors
```

### Scopes

The scope is a `scopeXxx()` or a `#[Scope]` method of the model. Arguments
are converted according to the signature: `int`, `float`, `bool`, `string`,
`array`, backed enum (`::from()`), `CarbonImmutable` or `Carbon` (`Y-m-d`
format).

All options are written as **named arguments**, by parameter name:

```php
$scope->scope('recent')                 // public name
    ->method('publishedAfter')          // actual method (default: the name)
    ->default(date: fn () => now()->subWeek())
    ->rules(date: ['after:2020-01-01'])
    ->messages(date: ['after' => 'Too old.'])
    ->attribute(date: 'start date');

$scope->scope('ofTags')
    ->rules(tags: ['max:10'])           // the whole array
    ->each(tags: ['string']);           // each item

$scope->scope('near')                   // untyped parameters
    ->cast(lat: Type::Float, lng: Type::Float);

$scope->scope('ofStatus')
    ->cast(status: Status::class);      // backed enum

$scope->scope('priced')
    ->cast(amount: fn (string $v) => Money::parse($v));
```

- `cast()` is required for an untyped, `mixed`, union, non-backed enum or
  any other class parameter.
- A `cast()` closure that throws an exception yields a validation error.
- A `default()` value can be fixed or a closure. A mutable date (`now()`) is
  converted to the class expected by the scope.

### Context

Some scope arguments must come from the code, never from the client: the
current user, ids computed by a service… Declare them with `context()`:

```php
// scopeForUser(Builder $query, string $search, User $user, array $groupIds)
$scope->scope('forUser')->context('user', 'groupIds');
```

The URL only carries `search` (`?forUser[search]=foo`): a `forUser[user]`
key sent by the client is ignored. The code gives the context, by scope name,
when building the query:

```php
$query = $parameters->builder(context: [
    'forUser' => [
        'user' => $user,
        'groupIds' => fn () => $groups->idsFor($user),
    ],
]);
```

- A closure is only called if the client requested the scope, unless the
  parameter itself is typed `Closure` or `callable`.
- A scope whose arguments all come from the context is a boolean toggle:
  `?mine=1` for `scopeMine(Builder $query, User $user)`.
- `default()` or the PHP default replaces a missing context; otherwise
  applying the scope throws a `LogicException`.
- A context key for an undeclared scope, or for an argument not declared with
  `context()`, throws an `InvalidArgumentException`.

### Sorting

```php
$sort->sort('date')->column('created_at');
$sort->sort('title');
$sort->default('date', 'desc')->default('title', 'asc');
```

In `cursor` mode, the primary key is added as the last sort criterion so
that pages remain stable.

### Relations

```php
$relation->relation('author')->fields(['id', 'name']);
$relation->relation('author.profile');  // nested relation
```

Remember to include in `fields()` the keys the relation needs.

### Pagination

```php
$pagination
    ->default('cursor')                 // default mode
    ->limit(25)                         // default items per page
    ->maxLimit(100)                     // accepted limit
    ->allow('offset', 'cursor', 'none');
```

## Configuration errors

An inconsistent config throws a
`StructuraPhp\QueryBuilder\Exception\ConfigurationException` when the query
is read, before the client input is validated:

- invalid name (only letters, digits and `_`, not starting with a digit),
  reserved or declared twice;
- filter without `type()`;
- scope or relation that does not exist on the model;
- scope option for an argument that does not exist, or passed without a name;
- `cast()`, `rules()`, `each()`, `messages()` or `attribute()` on a context
  argument;
- scope parameter neither typed nor cast, or `cast()` that is not a `Type`,
  a backed enum or a closure;
- undeclared default sort or direction other than `asc`/`desc`;
- unknown pagination mode, default mode not allowed, `limit` outside
  `1…maxLimit`.

## Known limitations

- No aliases for relations.
- No filter on a relation column (`author.name`), no `OR` groups, no `in`
  mode: use a scope.
- Scopes defined on a custom Builder or as macros are not supported.
- An integer value outside the column range (PostgreSQL `int4`) is not
  detected: add a `max:` rule to the filter.
