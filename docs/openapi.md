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

`dedoc/scramble` is not a dependency of the library: the extension only works
where Scramble is installed.
