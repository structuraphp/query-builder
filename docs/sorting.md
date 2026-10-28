# Sorting

**English** · [Français](sorting.fr.md) · [Documentation](README.md)

## Declaring sorts

```php
public function sort(SortInterface $sort): void
{
    $sort->sort('date')->column('created_at');  // public name, actual column
    $sort->sort('title');                       // column: the name
    $sort->default('date', Direction::Desc);    // when the client sends none
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
$sort->default('date', Direction::Desc)->default('title'); // Asc by default
```

```sql
-- GET /articles
ORDER BY articles.created_at DESC, articles.title ASC
```

As soon as the client sends `sort`, it replaces the defaults entirely.

In `cursor` pagination, the primary key is added as the last sort criterion
so that pages stay stable (see [Pagination](pagination.md)).

## Sorting on a count

`count()` sorts on the number of related models instead of a column:

```php
$sort->sort('comments')->count('comments');
```
```sql
-- GET /articles?sort[comments]=desc
SELECT articles.*, (SELECT count(*) FROM comments
    WHERE articles.id = comments.article_id) AS comments_count
FROM articles ORDER BY comments_count DESC
```

- `comments_count` is added to the response, even if the client did not ask
  for it with `count`; asked for both ways, it is computed once.
- The relation does not need to be declared countable (see
  [Relations](relations.md#counting-relations)). If it is, with a condition,
  the sort counts with this condition and its `count`
  [context](context.md#relation-and-count-conditions), so `comments_count`
  has one meaning; otherwise every related model is counted.
- Cursor pagination cannot sort on a computed count:
  `?pagination=cursor&sort[comments]=desc` is rejected on `sort`, and
  `?pagination=cursor` with a default count sort on `pagination`
  (`The selected … is invalid.`).

## Validation errors

| URL                | Error                                             |
|--------------------|---------------------------------------------------|
| `?sort[views]=asc` | `sort`: The selected sort is invalid.            |
| `?sort[title]=up`  | `sort.title`: The selected sort.title is invalid. |
| `?sort=title`      | `sort`: The selected sort is invalid.            |

Without any declared sort, `sort` is forbidden: `The sort field is
prohibited.`

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- sort declared twice, or not made of letters, digits and `_` only;
- default sort that is not declared;
- `column()` and `count()` on the same sort, or `count()` of a relation that
  is not a method of the model;
- default sort on a count with `cursor` as default pagination;
- sort on a column of the model's `$hidden` while `cursor` pagination is
  allowed: the cursor would expose its values.
