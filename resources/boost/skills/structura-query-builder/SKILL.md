---
name: structura-query-builder
description: Write or change query classes of structuraphp/query-builder, the Laravel library that turns HTTP query strings into Eloquent queries (filters, scopes, scope context, sorting, relations and their conditions, pagination, QueryRequest, QueryParameters). Use when declaring an API list endpoint, adding a filter, scope, sort or relation to a query class, or testing one.
---

# StructuraPHP Query Builder

## When to use this skill

Use it when a class implements `FromModel`, extends `QueryRequest`, or uses
`QueryParameters`, and when a list endpoint needs filters, sorting, eager
loading or pagination from the URL.

## Imports

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
use StructuraPhp\QueryBuilder\Enum\Direction;
use StructuraPhp\QueryBuilder\Enum\PaginationMode;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Service\QueryRequest;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
```

## Configuration methods

Each `With*` contract adds one method: `filter(FilterInterface)`,
`scope(ScopeInterface)`, `sort(SortInterface)`,
`relation(RelationInterface)`, `paginate(PaginationInterface)`. Each one is a
flat list of calls on its parameter, with literal names: no `if`, loop,
helper, closure receiving the parameter or `parent::` call (a closure given
to `constrain()` is fine). PHPStan reports anything else
(`structuraQueryBuilder.flatConfiguration`).

The query class is resolved by the container: its constructor may receive
dependencies, but the configuration must not depend on them.

## Filters

```php
public function filter(FilterInterface $filter): void
{
    $filter->filter('title')->type(Type::String);
    $filter->filter('views')->type(Type::Integer)->maxItems(500);
    $filter->filter('statut')                  // name in the URL
        ->column('status')                     // column, default: the name
        ->type(Type::String)
        ->rules(['in:draft,published'])        // added to the type rules
        ->messages(['in' => 'Invalid status.']) // by rule name
        ->attribute('status');                 // name in error messages
    $filter->filter('published')
        ->column('published_at')
        ->type(Type::Date)
        ->nullable();                          // adds the `null` mode
}
```

URL: `?title[value]=foo&title[mode]=like&title[not]=1`. `mode` defaults to
`eq`; `not=1` negates any mode (there is no `ne`).

| `Type`    | Modes                                | Value                                |
|-----------|--------------------------------------|--------------------------------------|
| `String`  | `eq`, `like`, `in`                   | at most 256 chars (`maxLength()`)    |
| `Integer` | `eq`, `gt`, `lt`, `gte`, `lte`, `in` | integer                              |
| `Float`   | `eq`, `gt`, `lt`, `gte`, `lte`, `in` | finite number                        |
| `Boolean` | `eq`                                 | `1` or `0` only                      |
| `Date`    | `eq`, `gt`, `lt`, `gte`, `lte`       | `Y-m-d`, compared by day             |

- `in` takes a list (`?views[value][]=10&views[value][]=20&views[mode]=in`),
  at most 256 items (`maxItems()`).
- `like` searches anywhere, case-insensitive, with `%`, `_`, `[` and `!`
  matched literally.
- `null` mode (with `nullable()`) takes no value: `?published[mode]=null`.
- No filter on a relation column and no `OR` between filters: write a scope.

## Scopes

A scope calls a model scope (`scopeX()` or `#[Scope]`), or a method of the
model's custom builder, with arguments from the URL validated from the PHP
signature.

```php
public function scope(ScopeInterface $scope): void
{
    $scope->scope('popular');                  // scopePopular(Builder $q)
    $scope->scope('recent')
        ->method('minViews')                   // public name != method
        ->default(min: 50);                    // or fn () => ...
    $scope->scope('ofStatuses')
        ->rules(statuses: ['max:2'])           // the whole array
        ->each(statuses: ['in:draft,published']); // each item
}
```

- Without arguments, or when every argument has a default, the scope is a
  boolean: `?popular=1`. With arguments: `?minViews[min]=10`.
- An argument without a default is required.
- Every option takes named arguments matching the PHP parameters:
  `default()`, `rules()`, `each()`, `nested()`, `messages()`,
  `attribute()`, `cast()`, `context()`. A positional option is an error.

Types read from the signature without `cast()`: `int`, `float`, `bool`,
`string`, `array` (flat list), backed enums, `CarbonImmutable`,
`DateTimeInterface` (`Y-m-d`, received as `CarbonImmutable`) and `Carbon`.
Untyped, `mixed`, union or any other class needs a cast:

```php
$scope->scope('near')->cast(lat: Type::Float, lng: Type::Float);
$scope->scope('ofStatus')->cast(status: Status::class); // backed enum
$scope->scope('viewsInRange')
    ->nested(range: [
        'min' => ['required', 'integer'],
        'max' => ['required', 'integer', 'gte:viewsInRange.range.min'],
    ])
    ->cast(range: fn (array $v) => new Range((int) $v['min'], (int) $v['max']));
```

- `nested()` takes rules by dot path inside the argument (`'*.min'` for a
  list of objects); a rule pointing to another field uses the full path.
- A closure cast only runs on valid input; if it throws, the client gets a
  validation error on the argument.
- Builder macros are not scopes.

## Context

Arguments that come from the server, never from the client:

```php
// scopeOwnedBy(Builder $query, string $status, Author $author)
$scope->scope('ownedBy')->context('author');

$query = $parameters->builder(context: [
    'ownedBy' => ['author' => $request->user()],
    'among' => ['ids' => fn () => $catalog->visibleIds($user)], // lazy
]);
```

- A context argument takes no `cast()`, `rules()`, `each()`, `nested()`,
  `messages()` or `attribute()`, and a client value for it is ignored.
- A closure is only called when the client requests the scope; it receives,
  by name, the scope's URL arguments it declares.
- A requested scope with missing context and no default throws a
  `LogicException`; a context key for an undeclared scope or argument throws
  an `InvalidArgumentException`.
- `with` and `count` context keys hold the values of relation conditions
  (see Relations), never scope context.

## Sorting

```php
public function sort(SortInterface $sort): void
{
    $sort->sort('date')->column('created_at');
    $sort->sort('title');
    $sort->sort('comments')->count('comments'); // sort on a relation count
    $sort->default('date', Direction::Desc)->default('title');
}
```

URL: `?sort[date]=desc&sort[title]=asc`; key order sets the priority, and
any `sort` replaces the defaults. A count sort cannot be used with cursor
pagination. Never sort on a column of the model's `$hidden` (or
`#[Hidden]`) while cursor pagination is allowed.

## Relations

```php
public function relation(RelationInterface $relation): void
{
    $relation->relation('author')->fields(['id', 'name']);
    $relation->relation('author.profile');     // parent must be declared
    $relation->count('comments');              // countable, not loadable
}
```

URL: `?with=author,author.profile&count=comments`. `fields()` must keep the
keys the relation needs (`id` for a `belongsTo`, the foreign key for a
`hasMany`). Only root relations can be counted. No alias. `count()` returns
the configuration of the count, not `$relation`: one `count()` per
statement, never `->count('a')->count('b')`.

### Conditions

`constrain()` limits the rows loaded or counted; the client never chooses
the condition:

```php
// Category::products(): HasMany<Product, $this>
$relation->relation('products')
    ->fields(['id', 'category_id', 'name'])
    ->constrain(fn (HasMany $query) => $query->where('state', State::Active));
$relation->count('products')
    ->constrain(fn (ProductBuilder $query, User $user) => $query->visibleTo($user));

$query = $parameters->builder(context: [
    'count' => ['products' => ['user' => fn () => $request->user()]],
]);
```

- The closure receives the relation for `relation()` (`HasMany`,
  `BelongsToMany`... with `wherePivot()`), the related model's builder for
  `count()` (its custom builder if any, else `Builder`). `fields()` applies
  after it; the last `constrain()` wins.
- From the second one, closure parameters receive by name the values given
  under the `with` (by relation path) or `count` (by count) context key. A
  closure value is called without arguments, only when the relation is
  loaded, counted or sorted on (unless the parameter is typed `Closure` or
  `callable`).
- A count sort on a declared `count()` applies its condition and `count`
  context, so `{relation}_count` keeps one meaning.
- A missing value without PHP default throws a `LogicException` when
  needed; an undeclared relation or count, or an unknown parameter name,
  throws an `InvalidArgumentException` at every `builder()`.
- Type the first parameter with the declared relation type (`HasMany`,
  `Relation`) or the builder (`ProductBuilder`, `Builder`), or leave it
  untyped; generics like `HasMany<Product, Category>` go in a `@param`
  PHPDoc, not the native type. PHPStan types the parameter when
  the query class is `final`, `getModel()` returns `X::class` and the
  relation method declares its generics (`@return HasMany<Product, $this>`),
  and reports an incompatible native type as `argument.type`.

## Pagination

```php
public function paginate(PaginationInterface $pagination): void
{
    $pagination
        ->default(PaginationMode::Cursor)
        ->limit(25)
        ->maxLimit(100)
        ->allow(PaginationMode::Offset, PaginationMode::Cursor);
}
```

Defaults: `Offset`, limit 50, max 100, `Offset` and `Cursor` allowed. URL:
`?pagination=offset&limit=20&page=2`, `?pagination=cursor&cursor=...`,
`?pagination=none`. `paginate()` returns a `LengthAwarePaginator`, a
`CursorPaginator` or a `Collection`.

## Reserved and invalid names

`with`, `count`, `sort`, `pagination`, `page`, `cursor` and `limit` are
reserved. Names use letters, digits and `_` only, and a filter and a scope
cannot share a name.

## Using the parameters

```php
// Controller: pass the value object to a domain service
public function index(ArticleQueryRequest $request, ArticleService $service)
{
    return $service->list($request->parameters());
}

// Domain service: refine the builder, then paginate
public function list(QueryParameters $parameters)
{
    $query = $parameters->builder();
    $query->where('tenant_id', $this->tenant->id);

    return $parameters->paginate($query);
}
```

`QueryRequest` reads the query string only, never the body. In HTTP tests,
put the parameters in the URL:
`$this->getJson('/articles?title[value]=foo&sort[date]=desc')`.

## Checking your work

Configuration errors throw a `ConfigurationException` when the query is
read. Run PHPStan to catch them before any call: `scopeNotFound` (unknown
scope or `method()`), `configuration` (every other runtime error, same
message) and `flatConfiguration`, all under the `structuraQueryBuilder.`
identifier prefix.
