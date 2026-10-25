# Pagination

[English](pagination.md) · **Français** · [Documentation](README.fr.md)

`QueryParameters::paginate()` pagine la requête dans le mode choisi par le
client, parmi ceux autorisés.

## Configuration

```php
public function paginate(PaginationInterface $pagination): void
{
    $pagination
        ->default('cursor')                 // mode par défaut
        ->limit(25)                         // éléments par page par défaut
        ->maxLimit(100)                     // limite la plus haute acceptée
        ->allow('offset', 'cursor', 'none'); // modes autorisés
}
```

Sans `WithPaginate`, ou sans appel :

| Option       | Défaut             |
|--------------|--------------------|
| `default()`  | `offset`           |
| `limit()`    | `50`               |
| `maxLimit()` | `100`              |
| `allow()`    | `offset`, `cursor` |

`none` doit être autorisé explicitement : il renvoie toutes les lignes.

## Paramètres

| Paramètre    | Mode     | Rôle                                 |
|--------------|----------|--------------------------------------|
| `pagination` | tous     | `offset`, `cursor` ou `none`         |
| `limit`      | paginé   | Éléments par page, de 1 à `maxLimit` |
| `page`       | `offset` | Numéro de page, à partir de 1        |
| `cursor`     | `cursor` | Curseur donné par la page précédente |

Les exemples utilisent `->limit(2)->maxLimit(50)` et un tri par défaut sur
`created_at desc`.

## Offset

```sql
-- GET /articles?limit=1&page=2
SELECT count(*) AS aggregate
FROM articles
SELECT *
FROM articles
ORDER BY articles.created_at DESC
LIMIT 1 OFFSET 1
```

La réponse est un `LengthAwarePaginator` :

```json
{
    "current_page": 2,
    "data": [
        …
    ],
    "first_page_url": "…",
    "from": 2,
    "last_page": 3,
    "last_page_url": "…",
    "links": [
        …
    ],
    "next_page_url": "…",
    "path": "…",
    "per_page": 1,
    "prev_page_url": "…",
    "to": 2,
    "total": 3
}
```

Les URL des pages gardent la query string : filtres, scopes et tris restent
appliqués d'une page à l'autre.

## Curseur

```sql
-- GET /articles?pagination=cursor&limit=2
SELECT *
FROM articles
ORDER BY articles.created_at DESC, articles.id ASC
LIMIT 3
```

La clé primaire est ajoutée en dernier critère de tri, pour que deux lignes
de même date ne soient jamais sautées ou répétées d'une page à l'autre.
Aucune requête de comptage n'est exécutée : ça tient sur de grosses tables.
Elle ne sait pas trier sur un nombre de relations (voir
[Tri](sorting.fr.md#trier-sur-un-nombre)).

La réponse est un `CursorPaginator` :

```json
{
    "data": [
        …
    ],
    "path": "…",
    "per_page": 2,
    "next_cursor": "eyJ…",
    "next_page_url": "…",
    "prev_cursor": null,
    "prev_page_url": null
}
```

La page suivante renvoie le curseur :

```
GET /articles?pagination=cursor&limit=2&cursor=eyJ…
```

Un curseur qui ne se décode pas est ignoré : la première page est renvoyée.
Un curseur décodé qui ne correspond pas à la requête (valeurs trafiquées) est
une erreur de validation sur `cursor`.

## Sans pagination

```sql
-- GET /articles?pagination=none
SELECT *
FROM articles
ORDER BY articles.created_at DESC
```

La réponse est la liste des lignes, sans clés de pagination.

## Erreurs de validation

| URL                               | Erreur                                                 |
|-----------------------------------|--------------------------------------------------------|
| `?limit=100`                      | `limit` : The limit field must not be greater than 50. |
| `?page=0`                         | `page` : The page field must be at least 1.            |
| `?pagination=none` (non autorisé) | `pagination` : The selected pagination is invalid.     |

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- mode inconnu dans `default()` ou `allow()` ;
- mode par défaut non autorisé ;
- `limit()` hors de `1…maxLimit`.
