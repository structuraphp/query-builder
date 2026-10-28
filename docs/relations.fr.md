# Relations

[English](relations.md) · **Français** · [Documentation](README.fr.md)

Le client choisit quelles relations charger, parmi celles déclarées.

## Déclarer les relations

```php
public function relation(RelationInterface $relation): void
{
    $relation->relation('author')->fields(['id', 'name']);
    $relation->relation('comments');
    $relation->relation('author.profile');      // relation imbriquée
}
```

## Charger depuis l'URL

`with` prend une liste séparée par des virgules. Laravel exécute une requête
par relation, après la requête principale :

```sql
-- GET /articles?with=author,comments
SELECT *
FROM articles …
SELECT id, name
FROM authors
WHERE authors.id IN (1)
SELECT *
FROM comments
WHERE comments.article_id IN (2, 3)
```

Chaque article reçoit ses relations dans la réponse :

```json
{
    "id": 2,
    "title": "Bar",
    "author": {
        "id": 1,
        "name": "Alice"
    },
    "comments": []
}
```

Les espaces autour des noms sont ignorés (`?with=author, comments`).

## Colonnes chargées

`fields()` fixe les colonnes du modèle lié ; le client ne peut jamais les
choisir, donc une colonne sensible (`password`) ne sort jamais de la base.
Sans `fields()`, toutes les colonnes sont chargées.

Incluez les clés dont la relation a besoin : la clé primaire pour un
`belongsTo` (`id` pour `author`), la clé étrangère pour un `hasMany`
(`article_id` pour `comments`). Sans elles, Laravel ne peut pas rattacher
les modèles liés.

## Relations imbriquées

Une relation imbriquée se déclare avec son chemin complet, et se charge de
la même façon :

```
GET /articles?with=author.profile
```

Son parent (`author`) doit aussi être déclaré. Laravel charge chaque
relation du chemin : chacune est chargée avec ses propres `fields()`, donc
`?with=author.profile` ne charge jamais plus de colonnes d'`author` que
`?with=author`.

Seule la première relation du chemin (`author`) est vérifiée sur le modèle à
la lecture de la query : les suivantes dépendent des modèles liés.

## Compter des relations

`count()` permet au client de compter une relation, sans la charger :

```php
$relation->relation('author');      // chargeable avec `with`
$relation->count('comments');       // comptable avec `count`
```
```sql
-- GET /articles?count=comments
SELECT articles.*, (SELECT count(*) FROM comments
    WHERE articles.id = comments.article_id) AS comments_count
FROM articles
```

Chaque article reçoit `comments_count` dans la réponse. `count` prend une
liste séparée par des virgules, comme `with`.

Une relation comptable n'est pas chargeable, et inversement : on peut
exposer le nombre de commentaires sans exposer les commentaires. Seules les
relations racines du modèle peuvent être comptées. Pour trier sur un nombre,
voir [Tri](sorting.fr.md#trier-sur-un-nombre).

`count()` renvoie la configuration du comptage, pas `$relation` : déclarez
chaque comptage dans sa propre instruction (`->count('a')->count('b')` ne
fonctionne pas).

## Conditions sur les relations

`constrain()` limite les lignes chargées ou comptées avec une relation. La
condition est fixée par le code, jamais par le client.

### Condition fixe

```php
// Category::products(): HasMany<Product, $this>
$relation->relation('products')
    ->fields(['id', 'category_id', 'name'])
    ->constrain(fn (HasMany $query) => $query->where('state', State::Active));
```

```sql
-- GET /categories?with=products
SELECT id, category_id, name FROM products
WHERE products.category_id IN (1, 2) AND state = 'active'
```

- La closure reçoit la relation que Laravel charge (`HasMany`, `BelongsTo`,
  `BelongsToMany`…) : les méthodes de la relation (`wherePivot()`…) et du
  Builder du modèle lié (les scopes) sont disponibles. Sa valeur de retour
  est ignorée.
- `fields()` s'applique après la condition. Sur un `BelongsToMany`, une
  relation `…Through` ou une relation « one of many », ses colonnes sont
  préfixées par la table liée (`products.id`), comme Laravel le fait pour
  `with('products:id')`. Elle remplace un `select()` ou un `withCount()`
  fait dans la condition.
- Une relation imbriquée a sa propre condition : `?with=author.profile`
  applique la condition d'`author`, puis celle d'`author.profile`.
- Un nouvel appel à `constrain()` sur la même relation remplace la
  condition, comme `fields()`.

### Condition tirée du contexte

À partir du deuxième, les paramètres de la closure reçoivent, par nom, des
valeurs que le code fournit dans `builder()`, sous la clé `with` :

```php
$relation->relation('products')
    ->constrain(fn (HasMany $query, User $user) => $query->visibleTo($user));
```

```php
$query = $parameters->builder(context: [
    'with' => ['products' => ['user' => fn () => $request->user()]],
]);
```

Le contexte d'une condition suit les règles du
[contexte](context.fr.md#conditions-des-relations-et-des-comptages) des
scopes : une closure n'est appelée que si la relation est chargée, un
paramètre qui a une valeur par défaut peut être omis.

### Compter avec une condition

```php
$relation->count('products')
    ->constrain(fn (ProductBuilder $query) => $query->where('state', State::Active));
```

```sql
-- GET /categories?count=products
SELECT categories.*, (SELECT count(*) FROM products
    WHERE categories.id = products.category_id AND state = 'active')
    AS products_count
FROM categories
```

- La closure reçoit le Builder Eloquent du modèle lié, que `withCount()`
  passe : son Builder personnalisé s'il en a un (`ProductBuilder`), sinon
  `Builder`.
- Son contexte vient sous la clé `count` :
  `builder(context: ['count' => ['products' => ['user' => $user]]])`.
- La colonne garde son nom, `products_count` ; un tri sur ce nombre applique
  la même condition (voir [Tri](sorting.fr.md#trier-sur-un-nombre)).
- Une relation n'est comptée qu'une fois : pas de second comptage de la même
  relation avec une autre condition.

## Erreurs de validation

| URL                     | Erreur                                    |
|-------------------------|-------------------------------------------|
| `?with=tags`            | `with` : The selected with is invalid.    |
| `?with=author.password` | `with` : The selected with is invalid.    |
| `?with[]=author`        | `with` : The with field must be a string. |
| `?count=author`         | `count` : The selected count is invalid.  |

Chaque relation de la liste doit être déclarée : un chemin imbriqué n'est
pas autorisé parce que sa racine l'est.

Sans aucune relation déclarée, `with` est interdit : `The with field is
prohibited.` De même pour `count` sans aucun comptage déclaré.

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- relation ou comptage déclaré deux fois ;
- relation racine qui n'est pas une méthode du modèle ;
- relation imbriquée dont le parent n'est pas déclaré (`author.profile` sans
  `author`) ;
- comptage d'une relation imbriquée (`author.profile`).

## Limites connues

- Pas d'alias : l'URL utilise le nom de la relation.
- Pas de filtre sur une colonne de relation (`author.name`) : passez par un
  scope.
- Pas de condition choisie par le client : une condition ne lit que le
  contexte.
- Pas d'autre agrégat que le comptage (`withSum`, `withAvg`…).
