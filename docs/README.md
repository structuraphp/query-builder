# Documentation

**English** · [Français](README.fr.md)

This guide details every feature with its configuration, the URLs a client
sends, and what they produce: the SQL query, or the validation error (422).

- [Filters](filters.md): compare a column with a value.
- [Scopes](scopes.md): call a model scope, or a method of its builder, with
  validated arguments.
- [Context](context.md): give scope arguments from the code, never from the
  client.
- [Sorting](sorting.md): order the results.
- [Relations](relations.md): eager load relations.
- [Pagination](pagination.md): offset, cursor or no pagination.

For installation and the quick start, see the [README](../README.md).

## Requests and parameters

`QueryRequest` only validates. `parameters()` returns a `QueryParameters`, a
value object without HTTP dependency, that a domain service can receive:

```php
// Controller
public function index(ArticleQueryRequest $request, ArticleService $service)
{
    return $service->list($request->parameters());
}

// Domain service
public function list(QueryParameters $parameters)
{
    $query = $parameters->builder(); // filters, scopes, sorts and relations
    $query->where('tenant_id', $this->tenant->id);

    return $parameters->paginate($query);
}
```

- `builder()` returns an Eloquent `Builder` you can still modify. It takes
  the scope [context](context.md).
- `paginate()` returns a `LengthAwarePaginator` (`offset`), a
  `CursorPaginator` (`cursor`) or a `Collection` (`none`).
- The query class is resolved by the Laravel container: its constructor can
  receive dependencies.
- The request's own `messages()` and `attributes()` stay available and win
  over the generated ones.

From a command, a job or a test, `fromArray()` applies the same validation
and throws a `ValidationException` on invalid data:

```php
$parameters = QueryParameters::fromArray(app(ArticleQuery::class), [
    'title' => ['value' => 'laravel', 'mode' => 'like'],
    'limit' => 20,
]);

$page = $parameters->paginate($parameters->builder());
```

[Scramble](https://scramble.dedoc.co) documents a `QueryRequest` like any
form request: filters, scopes, sorts and pagination become query parameters
(`title[value]`, `sort[date]`…), context arguments stay hidden.

## Conventions

The examples use an `Article` model with an `Author` and `Comment`s:

| Column         | Type     |
|----------------|----------|
| `id`           | integer  |
| `author_id`    | integer  |
| `title`        | string   |
| `status`       | string   |
| `views`        | integer  |
| `rating`       | float    |
| `featured`     | boolean  |
| `published_at` | date     |
| `created_at`   | datetime |

Each example declares only the section it is about, in a query class:

```php
final class ArticleQuery implements FromModel, WithFilter
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        // the configuration of the example
    }
}
```

- URLs are shown decoded for readability: a client sends
  `?title%5Bvalue%5D=Foo` for `?title[value]=Foo`. Libraries such as
  [qs](https://github.com/ljharb/qs) build them from an object.
- SQL is shown as SQLite runs it, simplified (`articles.title` for
  `"articles"."title"`).
- Error messages are Laravel's English ones: they follow the application
  locale.
- Keys the query does not declare (`?utm_source=…`) are ignored.

## Known limitations

- No filter on a relation column (`author.name`), no `OR` groups between
  filters, no `in` mode: use a scope.
- No alias and no constraint on loaded relations (see
  [Relations](relations.md)).
- Scopes defined as builder macros are not supported.
- An integer value outside the column range (PostgreSQL `int4`) is not
  detected: add a `max:` rule to the filter.
