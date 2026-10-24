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

Seule la première relation du chemin (`author`) est vérifiée à la lecture de
la query : les suivantes dépendent des modèles liés.

## Erreurs de validation

| URL                     | Erreur                                    |
|-------------------------|-------------------------------------------|
| `?with=tags`            | `with` : The selected with is invalid.    |
| `?with=author.password` | `with` : The selected with is invalid.    |
| `?with[]=author`        | `with` : The with field must be a string. |

Chaque relation de la liste doit être déclarée : un chemin imbriqué n'est
pas autorisé parce que sa racine l'est.

Sans aucune relation déclarée, `with` est interdit : `The with field is
prohibited.`

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- relation déclarée deux fois ;
- relation racine qui n'est pas une méthode du modèle.

## Limites connues

- Pas d'alias : l'URL utilise le nom de la relation.
- Pas de filtre sur une colonne de relation (`author.name`) : passez par un
  scope.
- Pas de contrainte sur la relation chargée (seulement certains
  commentaires) : pas encore supporté.
