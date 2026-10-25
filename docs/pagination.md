# Pagination

**English** · [Français](pagination.fr.md) · [Documentation](README.md)

`QueryParameters::paginate()` paginates the query in the mode chosen by the
client, among the allowed ones.

## Configuration

```php
public function paginate(PaginationInterface $pagination): void
{
    $pagination
        ->default('cursor')                 // default mode
        ->limit(25)                         // default items per page
        ->maxLimit(100)                     // highest limit accepted
        ->allow('offset', 'cursor', 'none'); // allowed modes
}
```

Without `WithPaginate`, or without a call:

| Option       | Default            |
|--------------|--------------------|
| `default()`  | `offset`           |
| `limit()`    | `50`               |
| `maxLimit()` | `100`              |
| `allow()`    | `offset`, `cursor` |

`none` must be allowed explicitly: it returns every row.

## Parameters

| Parameter    | Mode      | Purpose                              |
|--------------|-----------|--------------------------------------|
| `pagination` | all       | `offset`, `cursor` or `none`         |
| `limit`      | paginated | Items per page, from 1 to `maxLimit` |
| `page`       | `offset`  | Page number, from 1                  |
| `cursor`     | `cursor`  | Cursor given by the previous page    |

The examples use `->limit(2)->maxLimit(50)` and a default sort on
`created_at desc`.

## Offset

```sql
-- GET /articles?limit=1&page=2
SELECT count(*) AS aggregate FROM articles
SELECT * FROM articles ORDER BY articles.created_at DESC LIMIT 1 OFFSET 1
```

The response is a `LengthAwarePaginator`:

```json
{
  "current_page": 2,
  "data": [ … ],
  "first_page_url": "…", "from": 2, "last_page": 3, "last_page_url": "…",
  "links": [ … ], "next_page_url": "…", "path": "…", "per_page": 1,
  "prev_page_url": "…", "to": 2, "total": 3
}
```

The page URLs keep the query string: filters, scopes and sorts stay applied
from page to page.

## Cursor

```sql
-- GET /articles?pagination=cursor&limit=2
SELECT * FROM articles ORDER BY articles.created_at DESC, articles.id ASC LIMIT 3
```

The primary key is added as the last sort criterion, so that two rows with
the same date are never skipped or repeated between pages. No count query
runs: it scales on large tables. It cannot sort on a relation count (see
[Sorting](sorting.md#sorting-on-a-count)).

The response is a `CursorPaginator`:

```json
{
  "data": [ … ],
  "path": "…", "per_page": 2,
  "next_cursor": "eyJ…", "next_page_url": "…",
  "prev_cursor": null, "prev_page_url": null
}
```

The next page sends the cursor back:

```
GET /articles?pagination=cursor&limit=2&cursor=eyJ…
```

A cursor that does not decode is ignored: the first page is returned. A
decoded cursor that does not match the query (tampered values) is a
validation error on `cursor`.

## No pagination

```sql
-- GET /articles?pagination=none
SELECT * FROM articles ORDER BY articles.created_at DESC
```

The response is the list of rows, without pagination keys.

## Validation errors

| URL                              | Error                                                 |
|----------------------------------|-------------------------------------------------------|
| `?limit=100`                     | `limit`: The limit field must not be greater than 50. |
| `?page=0`                        | `page`: The page field must be at least 1.            |
| `?pagination=none` (not allowed) | `pagination`: The selected pagination is invalid.     |

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- unknown mode in `default()` or `allow()`;
- default mode that is not allowed;
- `limit()` outside `1…maxLimit`.
