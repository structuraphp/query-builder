# Scopes

**English** · [Français](scopes.fr.md) · [Documentation](README.md)

A scope calls a query scope of the model, or a method of its custom builder,
with arguments given by the client. The arguments are validated and
converted from the PHP signature.

## Where scopes come from

```php
class Article extends Model
{
    // classic scope
    public function scopePopular(Builder $query): void { … }

    // Laravel attribute (not private)
    #[Scope]
    protected function minViews(Builder $query, int $min = 10): void { … }
}

// method of a custom builder, declared with newEloquentBuilder() or
// #[UseEloquentBuilder(ArticleBuilder::class)]
class ArticleBuilder extends Builder
{
    public function viewsAtLeast(int $min): static { … }
}
```

```php
public function scope(ScopeInterface $scope): void
{
    $scope->scope('popular');
    $scope->scope('minViews');
    $scope->scope('viewsAtLeast');
}
```

- Like in Laravel, a builder method wins over a model scope of the same
  name.
- The wheres of a model scope are grouped by Laravel; those of a builder
  method are not: wrap its `OR` conditions in a `where()` closure.
- Eloquent's own builder methods (`delete()`, `update()`…) are never
  scopes.
- `method()` gives the scope a public name different from the method:
  `$scope->scope('recent')->method('minViews')`.

## Scope without arguments

It takes a boolean: `1` applies it, `0` ignores it.

```php
// scopePopular(Builder $query): where views >= 100
$scope->scope('popular');
```

```sql
-- GET /articles?popular=1
WHERE views >= 100
```

```sql
-- GET /articles?popular=0
-- no condition
```

```
GET /articles?popular=yes
```

```json
{"popular": ["The popular field must be true or false."]}
```

## Scope with arguments

The arguments are named like the PHP parameters, in any order:

```php
// scopePublishedBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $to)
$scope->scope('publishedBetween');
```

```sql
-- GET /articles?publishedBetween[from]=2024-01-01&publishedBetween[to]=2024-12-31
WHERE published_at BETWEEN '2024-01-01' AND '2024-12-31'
```

An argument without a default is required:

```
GET /articles?publishedBetween[from]=2024-01-01
```

```json
{"publishedBetween.to": ["The publishedBetween.to field is required when published between is present."]}
```

A scope with a required argument is requested as an object:
`?publishedBetween=1` is rejected. A scope whose arguments all have a
default also takes a boolean (see [Default values](#default-values)).

## Types

The validation and the conversion come from the PHP type of the parameter:

| PHP type                                | Rule                | The scope receives         |
|-----------------------------------------|---------------------|----------------------------|
| `int`                                   | `integer`           | `int`                      |
| `float`                                 | `numeric`           | `float`                    |
| `bool`                                  | `boolean`           | `bool`                     |
| `string`                                | `string`            | `string`                   |
| `array`                                 | `array`             | `array` (see below)        |
| backed enum                             | enum                | the enum case (`::from()`) |
| `CarbonImmutable`, `DateTimeInterface`… | `date_format:Y-m-d` | `CarbonImmutable`          |
| `Carbon`                                | `date_format:Y-m-d` | `Carbon`                   |

Any other type (untyped, `mixed`, union, other class) needs a `cast()`.

### Enum

```php
// scopeOfStatus(Builder $query, Status $status)
$scope->scope('ofStatus');
```

```sql
-- GET /articles?ofStatus[status]=draft
WHERE status = 'draft'
```

```
GET /articles?ofStatus[status]=archived
```

```json
{"ofStatus.status": ["The selected ofStatus.status is invalid."]}
```

### Array

Without `each()` or `nested()`, an `array` argument is a flat list: an item
that is itself an array is rejected. `each()` adds rules to every item:

```php
// scopeOfStatuses(Builder $query, array $statuses)
$scope->scope('ofStatuses')
    ->rules(statuses: ['max:2'])                     // the whole array
    ->each(statuses: ['in:draft,published']);       // each item
```

```sql
-- GET /articles?ofStatuses[statuses][]=draft&ofStatuses[statuses][]=published
WHERE status IN ('draft', 'published')
```

```
GET /articles?ofStatuses[statuses][]=archived
```

```json
{"ofStatuses.statuses.0": ["The selected ofStatuses.statuses.0 is invalid."]}
```

## Default values

An argument with a default becomes optional. `default()` wins over the PHP
default, and can be a closure, evaluated on each request:

```php
// minViews(Builder $query, int $min = 10)
$scope->scope('recent')
    ->method('minViews')
    ->default(min: 50);                 // or fn () => …
```

```sql
-- GET /articles?recent[min]=10
WHERE views >= 10
```

When every argument has a default, the scope also takes a boolean: `1`
applies it with the defaults, `0` ignores it.

```sql
-- GET /articles?recent=1
WHERE views >= 50
```

A mutable date (`now()`) given as default is converted to the class the
scope expects.

## Options by argument

Every option takes **named arguments**, by parameter name:

```php
$scope->scope('recent')
    ->method('publishedAfter')
    ->default(date: fn () => now()->subWeek())
    ->rules(date: ['after:2020-01-01'])               // added to the type rule
    ->messages(date: ['after' => 'Too old.'])          // by rule name
    ->attribute(date: 'start date');                   // name in errors
```

Without `attribute()`, an argument is named `scope.argument` in the errors (`ofStatus.status`).

## Casts

`cast()` forces the conversion of an argument:

```php
// scopeNear(Builder $query, $lat, $lng): untyped parameters
$scope->scope('near')->cast(lat: Type::Float, lng: Type::Float);

// a backed enum
$scope->scope('ofStatus')->cast(status: Status::class);

// any closure
$scope->scope('priced')->cast(amount: fn (string $v) => Money::parse($v));
```

```sql
-- GET /articles?near[lat]=1.5&near[lng]=60
WHERE views BETWEEN 1.5 AND 60
```

A closure cast runs once the rules of its argument passed, and receives the
request value (strings, arrays). If it throws an exception, the client gets
a validation error on the argument (`The selected near.lat is invalid.`).

## Structures with `nested()`

`nested()` takes Laravel rules by dot path inside the argument: lists of
objects, objects, sub-arrays, at any depth. The rules only apply when the
argument is given.

```php
// scopeInRanges(Builder $query, array $ranges)
$scope->scope('inRanges')
    ->nested(ranges: [
        '*' => ['array:min,max'],                   // each item: {min, max}
        '*.min' => ['required', 'integer'],
        '*.max' => ['required', 'integer', 'gte:inRanges.ranges.*.min'],
    ])
    ->messages(ranges: ['*.min.required' => 'Min is required.'])
    ->attribute(ranges: ['*.min' => 'minimum', '*.max' => 'maximum']);
```

```sql
-- GET /articles?inRanges[ranges][0][min]=1&inRanges[ranges][0][max]=10
--     &inRanges[ranges][1][min]=100&inRanges[ranges][1][max]=1000
WHERE (views BETWEEN '1' AND '10' OR views BETWEEN '100' AND '1000')
```

```
GET /articles?inRanges[ranges][0][min]=30&inRanges[ranges][0][max]=20
```

```json
{"inRanges.ranges.0.max": ["The maximum field must be greater than or equal to 30."]}
```

- A rule that points to another field (`gte:`, `required_with:`…) uses its
  full path.
- `each(x: [...])` is a shortcut for `nested(x: ['*' => [...]])`.
- `messages()` and `attribute()` take the same paths.
- The values stay strings (`'1'`): convert them with a closure cast.

### Building an object

```php
// scopeViewsInRange(Builder $query, Range $range)
$scope->scope('viewsInRange')
    ->nested(range: [
        'min' => ['required', 'integer'],
        'max' => ['required', 'integer'],
    ])
    ->cast(range: fn (array $v) => new Range((int) $v['min'], (int) $v['max']));
```

```sql
-- GET /articles?viewsInRange[range][min]=10&viewsInRange[range][max]=100
WHERE views BETWEEN 10 AND 100
```

The closure only runs on a valid structure: with `min` missing, the client
only gets the error of `nested()`.

## Configuration errors

A `ConfigurationException` is thrown when the query is read:

- scope that exists neither on the model nor on its builder;
- name reserved, declared twice (also against a filter), or not made of
  letters, digits and `_` only;
- option for an argument the scope does not have, or passed without a name;
- parameter that is neither typed nor cast, or `cast()` that is not a
  `Type`, a backed enum or a closure.
