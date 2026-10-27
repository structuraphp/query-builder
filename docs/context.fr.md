# Contexte

[English](context.md) · **Français** · [Documentation](README.fr.md)

Certains arguments de scope doivent venir du code, jamais du client :
l'utilisateur courant, le client dont parle la page, des identifiants
calculés par un service… On les déclare comme contexte, et on les fournit au
moment de construire la requête. Les conditions des relations prennent leur
contexte de la même façon.

## Déclarer des arguments de contexte

```php
// scopeOwnedBy(Builder $query, string $status, Author $author)
$scope->scope('ownedBy')->context('author');
```

`author` n'a aucune règle de validation et n'est jamais lu dans l'URL : seul
`status` vient du client.

## Fournir le contexte

Le code fournit le contexte par nom de scope, dans `builder()` :

```php
public function index(ArticleQueryRequest $request)
{
    $parameters = $request->parameters();
    $query = $parameters->builder(context: [
        'ownedBy' => ['author' => $request->user()],
    ]);

    return $parameters->paginate($query);
}
```

```sql
-- GET /articles?ownedBy[status]=draft
WHERE status = 'draft' AND author_id = 1
```

Une valeur envoyée par le client pour un argument de contexte est ignorée :

```sql
-- GET /articles?ownedBy[status]=draft&ownedBy[author]=99
WHERE status = 'draft' AND author_id = 1
```

## Scope dont tous les arguments viennent du contexte

C'est un interrupteur booléen, comme un scope sans argument :

```php
// scopeAmong(Builder $query, array $ids)
$scope->scope('among')->context('ids');
```

```php
$query = $parameters->builder(context: ['among' => ['ids' => [1, 2]]]);
```

```sql
-- GET /articles?among=1
WHERE id IN (1, 2)
```

## Calculer le contexte à la demande

Une closure donnée comme contexte n'est appelée que si le client a demandé
le scope : un service coûteux n'est pas sollicité pour rien.

```php
$query = $parameters->builder(context: [
    'among' => ['ids' => fn () => $catalog->visibleIds($user)],
]);
```

La closure reçoit, par nom, les arguments du scope venant de l'URL, une fois
validés et convertis (un enum reste un enum, une date un
`CarbonImmutable`) :

```php
// scopeForUser(Builder $query, Status $status, string $search, array $groupIds)
$scope->scope('forUser')->context('groupIds');

$query = $parameters->builder(context: [
    'forUser' => [
        'groupIds' => fn (Status $status, string $search) => $groups->idsMatching(
            $user,
            $status,
            $search,
        ),
    ],
]);
```

- La closure ne reçoit que les paramètres qu'elle déclare : `fn () => …`
  fonctionne.
- Un paramètre de la closure qui n'est pas un argument URL du scope, et n'a
  pas de valeur par défaut, lève une `InvalidArgumentException`.
- Si le paramètre du scope est lui-même typé `Closure` ou `callable`, la
  closure est passée telle quelle, jamais appelée.

## Conditions des relations et des comptages

Les [conditions](relations.fr.md#conditions-sur-les-relations) des relations
chargées et comptées prennent leur contexte sous deux clés réservées :
`with` par chemin de relation, `count` par relation comptée. Une valeur va
au paramètre de la closure du même nom, à partir du deuxième :

```php
$relation->relation('products')
    ->constrain(fn (HasMany $query, User $user) => $query->visibleTo($user));
$relation->count('products')
    ->constrain(fn (ProductBuilder $query, User $user) => $query->visibleTo($user));
```

```php
$query = $parameters->builder(context: [
    'with' => ['products' => ['user' => fn () => $request->user()]],
    'count' => ['products' => ['user' => fn () => $request->user()]],
]);
```

- Une closure est appelée sans argument, à la construction de la requête,
  seulement si la relation est chargée, comptée ou triée. Si le paramètre
  est lui-même typé `Closure` ou `callable`, la closure est passée telle
  quelle.
- La valeur par défaut PHP d'un paramètre remplace un contexte manquant.
- Une relation chargée, un comptage ou un tri sur un nombre dont le contexte
  manque, sans valeur par défaut, lève une `LogicException` :
  `Missing context argument user of relation products.` (ou
  `of count products.`). Charger `author.profile` demande aussi le contexte
  d'`author`.
- Ces clés lèvent une `InvalidArgumentException` à chaque `builder()`, même
  si la relation n'est pas demandée :
  - une relation non déclarée avec `relation()`, ou un comptage non déclaré
    avec `count()` : `Relation ghost is not declared.`,
    `Count ghost is not declared.` ;
  - une clé qui n'est pas un paramètre non variadique de la closure à partir
    du deuxième, ou toute clé d'une relation sans condition :
    `Relation products has no context argument ghost.`,
    `Count products has no context argument ghost.`

## Valeurs par défaut et erreurs

- `default()` ou la valeur par défaut PHP remplace un contexte manquant.
- Un scope demandé dont le contexte manque, sans valeur par défaut, lève une
  `LogicException` : c'est une erreur de développeur, pas du client.
- Une clé de contexte pour un scope non déclaré, ou pour un argument non
  déclaré avec `context()`, lève une `InvalidArgumentException`, même si le
  scope n'est pas demandé.
- `cast()`, `rules()`, `each()`, `nested()`, `messages()` ou `attribute()`
  sur un argument de contexte lève une `ConfigurationException`.
