# PHPStan rules

**English** · [Français](phpstan.fr.md) · [Documentation](README.md)

A query class is only checked when the endpoint is called: a scope that no
longer exists on the model answers a 500. The PHPStan rules shipped with the
library report these errors in your editor and in CI instead.

The rules read the code only: they never run your models, your query classes
or the container. Larastan is not required.

## Installation

With [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer),
the rules are loaded automatically. Otherwise, include them in your
`phpstan.neon`:

```neon
includes:
    - vendor/structuraphp/query-builder/extension.neon
```

## Rules

Each error has an identifier, so you can ignore one rule alone.

### `structuraQueryBuilder.scopeNotFound`

A declared scope matches neither a scope of the model nor a method of its
builder:

```php
public function scope(ScopeInterface $scope): void
{
    $scope->scope('published'); // Scope "published" does not exist on App\Models\Article.
}
```

A model whose builder cannot be determined statically (a non-final
`newEloquentBuilder()` return type, or an overridden `newModelQuery()`,
`newQuery()` or `resolveCustomBuilderClass()`) is not checked for scopes.

### `structuraQueryBuilder.configuration`

Every other error the library throws at runtime, with the same message:
duplicated or reserved names, filter without type, unknown relation, relation
without its parent, default sort not declared, sort on a hidden column under
cursor pagination, invalid scope arguments (unknown argument, missing cast,
argument given by the context)…

```php
public function filter(FilterInterface $filter): void
{
    $filter->filter('title');       // Filter "title" has no type.
    $filter->filter('page')->type(Type::String); // "page" is a reserved query parameter.
}
```

### `structuraQueryBuilder.flatConfiguration`

A configuration method must be a flat list of calls: no condition, helper or
`parent::` call, so the two rules above see the whole configuration.

```php
public function filter(FilterInterface $filter): void
{
    if ($this->admin) { // Query configuration must be a flat list of calls:
        $filter->filter('internal')->type(Type::String); // no condition, helper or parent call.
    }
}
```

Query classes configured through injected closures are reported too: ignore
this identifier for them.

## Literal strings

Every name of the configuration (filters, scopes, sorts, relations, arguments)
is typed `literal-string`: PHPStan reports a name built from a variable or
from user input. A `literal-string` with several possible values is accepted,
but the checks that depend on it are skipped.

PHPStan reports a `literal-string` violation from level 7 only. Below, a
dynamic name passes, and the `scopeNotFound` and `configuration` rules skip
it silently.

### Upgrading

At level 7 or above, a project that passes non-literal names gets new
`argument.type` errors: build the name from literals, or ignore the error
where the value is known to be safe.

## Inherited configuration

A configuration method inherited from a parent class is checked for each
concrete query class that uses it. The errors are reported in the parent's
file.

## What is skipped

The rules never report what they cannot read: a name that is not a constant
string, a model they cannot resolve, a class not yet analysed, an anonymous
query class (its reflection only exists in the process that found it). With a
partial analysis (the editor analysing one file), the rule that needs the
whole project reports nothing.

## Ignoring an identifier

```neon
parameters:
    ignoreErrors:
        -
            identifier: structuraQueryBuilder.flatConfiguration
            path: tests/*
```
