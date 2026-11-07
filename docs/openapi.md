# OpenAPI

**English** · [Français](openapi.fr.md) · [Documentation](README.md)

[Scramble](https://scramble.dedoc.co) documents a `QueryRequest` like any
form request: filters, scopes, sorts and pagination become query parameters
(`title[value]`, `sort[date]`…), and context arguments stay hidden.

## The two forms of a filter value

A filter value takes one of two shapes, depending on its mode:

| Form   | URL                                                    | Mode               |
|--------|--------------------------------------------------------|--------------------|
| Simple | `?status[value]=active`                                | `eq` (default), `like`, `gt`… |
| List   | `?status[value][]=active&status[value][]=pending&status[mode]=in` | `in` |

The rule of `<name>.value` depends on the mode, and Scramble cannot read it.
It only sees the rule of each item, `<name>.value.*`, and documents the list:

```json
{"name": "status[value][]", "in": "query", "schema": {"type": "array", "items": {"type": "string"}}}
```

The simple form, the one most clients send, is missing from the document. A
client generated from it (openapi-typescript for example) has no
`status[value]` key and refuses `?status[value]=active`, which the API
accepts.

## Document the simple form

`ScalarFilterValueExtension` adds the simple form next to the list. Register
it in `config/scramble.php`:

```php
'extensions' => [
    StructuraPhp\QueryBuilder\Scramble\ScalarFilterValueExtension::class,
],
```

For each `<name>[value][]` parameter of an operation that also has
`<name>[mode]`, it adds a `<name>[value]` parameter just before it, typed as
one item of the list:

```json
{"name": "status[value]", "in": "query", "schema": {"type": "string"}}
{"name": "status[value][]", "in": "query", "schema": {"type": "array", "items": {"type": "string"}}}
```

- It also removes the `enum` that Scramble puts on the array itself: no
  array can match a list of strings.
- A parameter that is not a filter is left alone: a list without a
  `[mode]` sibling, or one that is not an array.
- Running it twice changes nothing.

## Document the relations

`with` and `count` take a comma separated list of the relations the query
declares, checked by a closure that Scramble cannot read: it documents a bare
`string`, and a generated client knows neither the names nor the format.

`RelationParametersExtension` reads them from the query of the request the
action takes. Register it next to the other one:

```php
'extensions' => [
    StructuraPhp\QueryBuilder\Scramble\ScalarFilterValueExtension::class,
    StructuraPhp\QueryBuilder\Scramble\RelationParametersExtension::class,
    StructuraPhp\QueryBuilder\Scramble\NumberBoundsExtension::class,
],
```

```json
{
  "name": "with",
  "in": "query",
  "description": "Relations to load, separated by commas: `author`, `comments`.",
  "schema": {"type": "string", "pattern": "^\\s*(author|comments)\\s*(,\\s*(author|comments)\\s*)*$"}
}
```

- A query that declares no relation (or no count) prohibits the parameter:
  it is removed from the operation.
- The pattern accepts spaces around the names, like the server.
- An action without a `QueryRequest` parameter is left alone.

## Drop the bounds of the server

Two bounds only guard the server, and are noise in the document:

- a float is `between` ±1.797e308, the largest float: its rule has to be
  readable by Scramble, which turns it into `minimum` and `maximum`;
- `page` is capped at `PHP_INT_MAX / maxLimit` (`92233720368547760` for a
  limit of 100), so that Laravel's offset stays an integer.

A client generated from the document rounds them, or refuses them.
`NumberBoundsExtension` drops every `minimum` or `maximum`, on a number or on
the items of a list, that a JSON number cannot hold exactly (beyond 2^53 - 1).
The other bounds stay: `limit` keeps its `maximum: 100`.

```json
{"name": "rating[value][]", "schema": {"type": "array", "items": {"type": "number"}}}
{"name": "page", "schema": {"type": "integer", "minimum": 1}}
```

`dedoc/scramble` is not a dependency of the library: the extension only works
where Scramble is installed.
