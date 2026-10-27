## StructuraPHP Query Builder

`structuraphp/query-builder` turns HTTP query strings into Eloquent queries.
A query class declares what the client may filter, scope, sort, load and
paginate; anything undeclared is ignored or rejected with a 422.

### Building an endpoint

- A query class implements `FromModel` and only the `With*` contracts it
  needs (`WithFilter`, `WithScope`, `WithSort`, `WithRelation`,
  `WithPaginate`), from `StructuraPhp\QueryBuilder\Contract\Query`.
- A request extends `StructuraPhp\QueryBuilder\Service\QueryRequest` and
  returns the query class from `getQuery()`: its validation rules are
  generated.
- The controller gets a `QueryParameters` from `$request->parameters()`,
  refines `builder()` and returns `paginate()`.

@verbatim
<code-snippet name="Query class, request and controller" lang="php">
final class ArticleQuery implements FromModel, WithFilter, WithSort
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('title')->type(Type::String);
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->default('date', Direction::Desc);
    }
}

final class ArticleQueryRequest extends QueryRequest
{
    protected function getQuery(): string
    {
        return ArticleQuery::class;
    }
}

public function index(ArticleQueryRequest $request)
{
    $parameters = $request->parameters();
    $query = $parameters->builder();
    $query->where('tenant_id', $request->user()->tenant_id);

    return $parameters->paginate($query);
}
</code-snippet>
@endverbatim

### Rules

- A configuration method is a flat list of calls on its parameter: no
  condition, loop, helper method or `parent::` call. Write another query
  class for another variant.
- Every name (filter, scope, sort, relation, scope argument) is a literal
  string, never built from a variable.
- Every filter has a `type()`. `column()` keeps schema names out of the URL;
  `fields()` sets the loaded columns of a relation.
- Scope options take named arguments, by PHP parameter name:
  `->default(min: 50)`, `->rules(date: ['after:2020-01-01'])`.
- A scope argument that must come from the server (current user, tenant,
  allowed ids) is declared with `->context('author')` and given in
  `builder(context: ['scope' => ['author' => $user]])`, never read from the
  request.
- A loaded or counted relation is limited with `constrain()`, never by the
  client. Its closure receives the relation (or, for a count, the related
  model's builder), then server values by parameter name, given under the
  `with` and `count` keys of the context. `count()` returns its own
  configuration: declare each count in its own statement.
- `with`, `count`, `sort`, `pagination`, `page`, `cursor` and `limit` are
  reserved names.
- Parameters are read from the query string only: test with
  `getJson('/articles?title[value]=foo')`, not with a JSON body.
- After changing a query class, run PHPStan: the `structuraQueryBuilder.*`
  rules report the configuration errors the library throws at runtime.

@verbatim
<code-snippet name="Relation conditions and their context" lang="php">
public function relation(RelationInterface $relation): void
{
    $relation->relation('products')
        ->constrain(fn (HasMany $query, User $user) => $query->visibleTo($user));
    $relation->count('products')
        ->constrain(fn (ProductBuilder $query) => $query->where('state', State::Active));
}

$query = $parameters->builder(context: [
    'with' => ['products' => ['user' => fn () => $request->user()]],
]);
</code-snippet>
@endverbatim

For filters, scopes, context, sorting, relations and pagination in detail,
use the `structura-query-builder` skill when it is available.
