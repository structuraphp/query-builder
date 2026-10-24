# Tri

[English](sorting.md) · **Français** · [Documentation](README.fr.md)

## Déclarer les tris

```php
public function sort(SortInterface $sort): void
{
    $sort->sort('date')->column('created_at');  // nom public, colonne réelle
    $sort->sort('title');                       // colonne : le nom
    $sort->default('date', 'desc');             // quand le client n'en envoie pas
}
```

## Trier depuis l'URL

Chaque clé est un tri déclaré, chaque valeur `asc` ou `desc` :

```sql
-- GET /articles?sort[title]=asc
ORDER BY articles.title ASC
```

L'ordre des clés donne la priorité :

```sql
-- GET /articles?sort[date]=asc&sort[title]=desc
ORDER BY articles.created_at ASC, articles.title DESC
```

## Tri par défaut

Sans `sort`, les tris par défaut s'appliquent, dans l'ordre de déclaration :

```php
$sort->default('date', 'desc')->default('title', 'asc');
```

```sql
-- GET /articles
ORDER BY articles.created_at DESC, articles.title ASC
```

Dès que le client envoie `sort`, il remplace entièrement les tris par
défaut.

En pagination `cursor`, la clé primaire est ajoutée en dernier critère de
tri pour que les pages restent stables (voir [Pagination](pagination.fr.md)).

## Erreurs de validation

| URL                | Erreur                                             |
|--------------------|----------------------------------------------------|
| `?sort[views]=asc` | `sort` : The sort field must be an array.          |
| `?sort[title]=up`  | `sort.title` : The selected sort.title is invalid. |
| `?sort=title`      | `sort` : The sort field must be an array.          |

Une clé de tri non déclarée est refusée avec le message de la règle `array:`
de Laravel, qui dit seulement que `sort` doit être un tableau.

Sans aucun tri déclaré, `sort` est interdit : `The sort field is
prohibited.`

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- tri déclaré deux fois, ou qui n'est pas fait que de lettres, chiffres et
  `_` ;
- tri par défaut non déclaré, ou direction autre que `asc`/`desc`.
