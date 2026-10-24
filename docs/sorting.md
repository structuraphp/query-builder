# Sorting

**English** · [Français](sorting.fr.md) · [Documentation](README.md)

## Declaring sorts

```php
public function sort(SortInterface $sort): void
{
    $sort->sort('date')->column('created_at');  // public name, actual column
    $sort->sort('title');                       // column: the name
    $sort->default('date', 'desc');             // when the client sends none
}
```

## Sorting from the URL

Each key is a declared sort, each value `asc` or `desc`:

```sql
-- GET /articles?sort[title]=asc
ORDER BY articles.title ASC
```

The order of the keys sets the priority:

```sql
-- GET /articles?sort[date]=asc&sort[title]=desc
ORDER BY articles.created_at ASC, articles.title DESC
```

## Default sort

Without `sort`, the defaults apply, in declaration order:

```php
$sort->default('date', 'desc')->default('title', 'asc');
```

```sql
-- GET /articles
ORDER BY articles.created_at DESC, articles.title ASC
```

As soon as the client sends `sort`, it replaces the defaults entirely.

In `cursor` pagination, the primary key is added as the last sort criterion
so that pages stay stable (see [Pagination](pagination.md)).

## Validation errors

| URL                | Error                                             |
|--------------------|---------------------------------------------------|
| `?sort[views]=asc` | `sort`: The sort field must be an array.          |
| `?sort[title]=up`  | `sort.title`: The selected sort.title is invalid. |
| `?sort=title`      | `sort`: The sort field must be an array.          |

An undeclared sort key is rejected with Laravel's `array:` rule message,
which only says that `sort` must be an array.

Without any declared sort, `sort` is forbidden: `The sort field is
prohibited.`

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- sort declared twice, or not made of letters, digits and `_` only;
- default sort that is not declared, or direction other than `asc`/`desc`.
