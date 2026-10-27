# Tri

[English](sorting.md) · **Français** · [Documentation](README.fr.md)

## Déclarer les tris

```php
public function sort(SortInterface $sort): void
{
    $sort->sort('date')->column('created_at');  // nom public, colonne réelle
    $sort->sort('title');                       // colonne : le nom
    $sort->default('date', Direction::Desc);    // quand le client n'en envoie pas
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
ORDER BY articles.created_at ASC, articles.title
DESC
```

## Tri par défaut

Sans `sort`, les tris par défaut s'appliquent, dans l'ordre de déclaration :

```php
$sort->default('date', Direction::Desc)->default('title'); // Asc par défaut
```

```sql
-- GET /articles
ORDER BY articles.created_at
DESC, articles.title ASC
```

Dès que le client envoie `sort`, il remplace entièrement les tris par
défaut.

En pagination `cursor`, la clé primaire est ajoutée en dernier critère de
tri pour que les pages restent stables (voir [Pagination](pagination.fr.md)).

## Trier sur un nombre

`count()` trie sur le nombre de modèles liés plutôt que sur une colonne :

```php
$sort->sort('comments')->count('comments');
```
```sql
-- GET /articles?sort[comments]=desc
SELECT articles.*, (SELECT count(*) FROM comments
    WHERE articles.id = comments.article_id) AS comments_count
FROM articles ORDER BY comments_count DESC
```

- `comments_count` est ajouté à la réponse, même si le client ne l'a pas
  demandé avec `count` ; demandé des deux façons, il n'est calculé qu'une
  fois.
- La relation n'a pas besoin d'être déclarée comptable (voir
  [Relations](relations.fr.md#compter-des-relations)). Si elle l'est, avec
  une condition, le tri compte avec cette condition et son
  [contexte](context.fr.md#conditions-des-relations-et-des-comptages)
  `count`, pour que `comments_count` n'ait qu'un sens ; sinon, tous les
  modèles liés sont comptés.
- La pagination par curseur ne sait pas trier sur un nombre calculé :
  `?pagination=cursor&sort[comments]=desc` est refusé sur `sort`, et
  `?pagination=cursor` avec un tri par défaut sur un nombre est refusé sur
  `pagination` (`The selected … is invalid.`).

## Erreurs de validation

| URL                | Erreur                                             |
|--------------------|----------------------------------------------------|
| `?sort[views]=asc` | `sort` : The selected sort is invalid.             |
| `?sort[title]=up`  | `sort.title` : The selected sort.title is invalid. |
| `?sort=title`      | `sort` : The selected sort is invalid.             |

Sans aucun tri déclaré, `sort` est interdit : `The sort field is
prohibited.`

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- tri déclaré deux fois, ou qui n'est pas fait que de lettres, chiffres et
  `_` ;
- tri par défaut non déclaré ;
- `column()` et `count()` sur le même tri, ou `count()` d'une relation qui
  n'est pas une méthode du modèle ;
- tri par défaut sur un nombre avec `cursor` comme pagination par défaut ;
- tri sur une colonne du `$hidden` du modèle alors que la pagination par
  curseur est autorisée : le curseur exposerait ses valeurs.
