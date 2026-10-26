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
- Pas de contrainte sur la relation chargée ou comptée (seulement certains
  commentaires) : pas encore supporté.
- Pas d'autre agrégat que le comptage (`withSum`, `withAvg`…).
