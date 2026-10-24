# Filters

**English** · [Français](filters.fr.md) · [Documentation](README.md)

A filter compares a column with a value given by the client.

## Declaring a filter

```php
public function filter(FilterInterface $filter): void
{
    $filter->filter('title')->type(Type::String);
}
```

The filter name is at the root of the query string, with three keys:

| Key     | Required | Purpose                                |
|---------|----------|----------------------------------------|
| `value` | yes      | Compared value                         |
| `mode`  | no (`=`) | Operator, depending on the filter type |
| `not`   | no       | `1` negates the condition              |

```sql
-- GET /articles?title[value]=Foo
WHERE articles.title = 'Foo'
```

A filter absent from the URL is not applied.

## Types and modes

The type is required. It sets the validation of `value` and the accepted
modes:

| `Type`    | Validation          | Modes                     |
|-----------|---------------------|---------------------------|
| `String`  | `string`            | `=`, `like`               |
| `Integer` | `integer`           | `=`, `>`, `<`, `>=`, `<=` |
| `Float`   | `numeric`           | `=`, `>`, `<`, `>=`, `<=` |
| `Boolean` | `boolean`           | `=`                       |
| `Date`    | `date_format:Y-m-d` | `=`, `>`, `<`, `>=`, `<=` |

### Integer and float

```php
$filter->filter('views')->type(Type::Integer);
$filter->filter('rating')->type(Type::Float);
```

```sql
-- GET /articles?views[value]=10&views[mode]=>=
WHERE articles.views >= 10
```

```sql
-- GET /articles?rating[value]=4.5&rating[mode]=<
WHERE articles.rating < 4.5
```

### `like`

`like` searches the value anywhere in the column:

```sql
-- GET /articles?title[value]=fo&title[mode]=like
WHERE articles.title LIKE '%fo%' ESCAPE '!'
```

`%`, `_` and `!` typed by the client are matched literally, never as
wildcards:

```sql
-- GET /articles?title[value]=50%&title[mode]=like
WHERE articles.title LIKE '%50!%%' ESCAPE '!'
```

The search ignores case: `ILIKE` on PostgreSQL; on MySQL, it depends on the
column collation.

### Boolean

```php
$filter->filter('featured')->type(Type::Boolean);
```

```sql
-- GET /articles?featured[value]=1
WHERE articles.featured = 1
```

In a query string, only `1` and `0` are booleans: `true` is rejected.

```
GET /articles?featured[value]=true
```

```json
{"featured.value": ["The featured field must be true or false."]}
```

### Date

The value is a `Y-m-d` day. The comparison works by day, even on a datetime
column, and never wraps the column in an SQL function, so its index stays
usable:

```php
$filter->filter('published')->column('published_at')->type(Type::Date);
```

| Mode | URL                                               | SQL                                                            |
|------|---------------------------------------------------|----------------------------------------------------------------|
| `=`  | `?published[value]=2024-01-15`                    | `published_at >= '2024-01-15' AND published_at < '2024-01-16'` |
| `>`  | `?published[value]=2024-01-15&published[mode]=>`  | `published_at >= '2024-01-16'`                                 |
| `>=` | `?published[value]=2024-01-15&published[mode]=>=` | `published_at >= '2024-01-15'`                                 |
| `<`  | `?published[value]=2024-01-15&published[mode]=<`  | `published_at < '2024-01-15'`                                  |
| `<=` | `?published[value]=2024-01-15&published[mode]=<=` | `published_at < '2024-01-16'`                                  |

```
GET /articles?published[value]=15/01/2024
```

```json
{"published.value": ["The published field must match the format Y-m-d."]}
```

## Negating with `not`

There is no `!=` mode: `not=1` negates any mode.

```sql
-- GET /articles?title[value]=Foo&title[not]=1
WHERE NOT (articles.title = 'Foo')
```

## Column, rules, messages and attribute

```php
$filter->filter('statut')               // public name, in the URL
    ->column('status')                  // actual column (default: the name)
    ->type(Type::String)
    ->rules(['in:draft,published'])     // Laravel rules on top of the type
    ->messages(['in' => 'Invalid status.']) // by rule name
    ->attribute('status');              // name shown in errors
```

- `column()` keeps the database schema out of the URL.
- `rules()` adds Laravel rules to the validation of `value`.
- `messages()` replaces the message of a rule, by rule name.
- `attribute()` names the filter in the default messages.

```
GET /articles?statut[value]=archived
```

```json
{"statut.value": ["Invalid status."]}
```

## Validation errors

| URL                                 | Error                                                             |
|-------------------------------------|-------------------------------------------------------------------|
| `?views[value]=abc`                 | `views.value`: The views field must be an integer.                |
| `?views[value]=10&views[mode]=like` | `views.mode`: The selected views is invalid.                      |
| `?title[mode]=like`                 | `title.value`: The title field is required when title is present. |
| `?title=Foo`                        | `title`: The title field must be an array.                        |
| `?title[value][]=Foo`               | `title.value`: The title field must be a string.                  |

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- filter without `type()`;
- name reserved (`with`, `sort`, `pagination`, `page`, `cursor`, `limit`),
  declared twice (also against a scope), or not made of letters, digits and
  `_` only.
