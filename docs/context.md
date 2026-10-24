# Context

**English** · [Français](context.fr.md) · [Documentation](README.md)

Some scope arguments must come from the code, never from the client: the
current user, the customer the page is about, ids computed by a service… They
are declared as context, and given when the query is built.

## Declaring context arguments

```php
// scopeOwnedBy(Builder $query, string $status, Author $author)
$scope->scope('ownedBy')->context('author');
```

`author` gets no validation rule and is never read from the URL: only
`status` comes from the client.

## Giving the context

The code gives the context by scope name, in `builder()`:

```php
public function index(ArticleQueryRequest $request)
{
    $parameters = $request->parameters();
    $query = $parameters->builder(context: [
        'ownedBy' => ['author' => $request->user()],
    ]);

    return $parameters->paginate($query);
}
```

```sql
-- GET /articles?ownedBy[status]=draft
WHERE status = 'draft' AND author_id = 1
```

A value sent by the client for a context argument is ignored:

```sql
-- GET /articles?ownedBy[status]=draft&ownedBy[author]=99
WHERE status = 'draft' AND author_id = 1
```

## Scope with context arguments only

It is a boolean toggle, like a scope without arguments:

```php
// scopeAmong(Builder $query, array $ids)
$scope->scope('among')->context('ids');
```

```php
$query = $parameters->builder(context: ['among' => ['ids' => [1, 2]]]);
```

```sql
-- GET /articles?among=1
WHERE id IN (1, 2)
```

## Computing the context lazily

A closure given as context is only called if the client requested the
scope, so a costly service is not called for nothing:

```php
$query = $parameters->builder(context: [
    'among' => ['ids' => fn () => $catalog->visibleIds($user)],
]);
```

The closure receives, by name, the scope arguments coming from the URL, once
validated and converted (an enum stays an enum, a date a `CarbonImmutable`):

```php
// scopeForUser(Builder $query, Status $status, string $search, array $groupIds)
$scope->scope('forUser')->context('groupIds');

$query = $parameters->builder(context: [
    'forUser' => [
        'groupIds' => fn (Status $status, string $search) => $groups->idsMatching(
            $user,
            $status,
            $search,
        ),
    ],
]);
```

- The closure only receives the parameters it declares: `fn () => …` works.
- A closure parameter that is not a URL argument of the scope, and has no
  default, throws an `InvalidArgumentException`.
- If the scope parameter is itself typed `Closure` or `callable`, the closure
  is passed as is, never called.

## Defaults and errors

- `default()` or the PHP default replaces a missing context.
- A requested scope whose context is missing, without a default, throws a
  `LogicException`: a developer error, not a client one.
- A context key for an undeclared scope, or for an argument not declared with
  `context()`, throws an `InvalidArgumentException`, even when the scope is
  not requested.
- `cast()`, `rules()`, `each()`, `nested()`, `messages()` or `attribute()`
  on a context argument throws a `ConfigurationException`.
