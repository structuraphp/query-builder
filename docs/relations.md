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

Only the first relation of the path (`author`) is checked when the query is
read: the nested ones depend on the related models.

## Validation errors

| URL                     | Error                                    |
|-------------------------|------------------------------------------|
| `?with=tags`            | `with`: The selected with is invalid.    |
| `?with=author.password` | `with`: The selected with is invalid.    |
| `?with[]=author`        | `with`: The with field must be a string. |

Every relation of the list must be declared: a nested path is not allowed
because its root is.

Without any declared relation, `with` is forbidden: `The with field is
prohibited.`

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- relation declared twice;
- root relation that is not a method of the model.

## Known limitations

- No alias: the URL uses the relation name.
- No filter on a relation column (`author.name`): use a scope.
- No constraint on the loaded relation (only some comments): not supported
  yet.
