# Relations

**English** · [Français](relations.fr.md) · [Documentation](README.md)

The client chooses which relations to eager load, among the declared ones.

## Declaring relations

```php
public function relation(RelationInterface $relation): void
{
    $relation->relation('author')->fields(['id', 'name']);
    $relation->relation('comments');
    $relation->relation('author.profile');      // nested relation
}
```

## Loading from the URL

`with` takes a comma-separated list. Laravel runs one query per relation,
after the main one:

```sql
-- GET /articles?with=author,comments
SELECT * FROM articles …
SELECT id, name FROM authors WHERE authors.id IN (1)
SELECT * FROM comments WHERE comments.article_id IN (2, 3)
```

Each article gets its relations in the response:

```json
{"id": 2, "title": "Bar", "author": {"id": 1, "name": "Alice"}, "comments": []}
```

Spaces around names are ignored (`?with=author, comments`).

## Loaded columns

`fields()` sets the columns of the related model; the client can never
choose them, so a sensitive column (`password`) never leaves the database.
Without `fields()`, every column is loaded.

Include the keys the relation needs: the primary key for a `belongsTo`
(`id` for `author`), the foreign key for a `hasMany` (`article_id` for
`comments`). Without them, Laravel cannot match the related models.

## Nested relations

A nested relation is declared with its full path, and loaded the same way:

```
GET /articles?with=author.profile
```

Its parent (`author`) must be declared too. Laravel loads every relation of
the path: each one is loaded with its own `fields()`, so
`?with=author.profile` never loads more columns of `author` than
`?with=author`.

Only the first relation of the path (`author`) is checked against the model
when the query is read: the nested ones depend on the related models.

## Counting relations

`count()` lets the client count a relation, without loading it:

```php
$relation->relation('author');      // loadable with `with`
$relation->count('comments');       // countable with `count`
```
```sql
-- GET /articles?count=comments
SELECT articles.*, (SELECT count(*) FROM comments
    WHERE articles.id = comments.article_id) AS comments_count
FROM articles
```

Each article gets `comments_count` in the response. `count` takes a
comma-separated list, like `with`.

A countable relation is not loadable, and the other way round: you can
expose the number of comments without exposing the comments. Only root
relations of the model can be counted. To sort on a count, see
[Sorting](sorting.md#sorting-on-a-count).

## Validation errors

| URL                     | Error                                    |
|-------------------------|------------------------------------------|
| `?with=tags`            | `with`: The selected with is invalid.    |
| `?with=author.password` | `with`: The selected with is invalid.    |
| `?with[]=author`        | `with`: The with field must be a string. |
| `?count=author`         | `count`: The selected count is invalid.  |

Every relation of the list must be declared: a nested path is not allowed
because its root is.

Without any declared relation, `with` is forbidden: `The with field is
prohibited.` The same goes for `count` without any declared count.

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- relation or count declared twice;
- root relation that is not a method of the model;
- nested relation whose parent is not declared (`author.profile` without
  `author`);
- count of a nested relation (`author.profile`).

## Known limitations

- No alias: the URL uses the relation name.
- No filter on a relation column (`author.name`): use a scope.
- No constraint on the loaded or counted relation (only some comments): not
  supported yet.
- No other aggregate than the count (`withSum`, `withAvg`…).
