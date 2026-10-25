# Filtres

[English](filters.md) · **Français** · [Documentation](README.fr.md)

Un filtre compare une colonne à une valeur fournie par le client.

## Déclarer un filtre

```php
public function filter(FilterInterface $filter): void
{
    $filter->filter('title')->type(Type::String);
}
```

Le nom du filtre est à la racine de la query string, avec trois clés :

| Clé     | Obligatoire | Rôle                               |
|---------|-------------|------------------------------------|
| `value` | oui         | Valeur comparée                    |
| `mode`  | non (`eq`)  | Opérateur, selon le type du filtre |
| `not`   | non         | `1` inverse la condition           |

```sql
-- GET /articles?title[value]=Foo
WHERE articles.title = 'Foo'
```

Un filtre absent de l'URL n'est pas appliqué.

## Types et modes

Le type est obligatoire. Il fixe la validation de `value` et les modes
acceptés :

| `Type`    | Validation          | Modes                                |
|-----------|---------------------|--------------------------------------|
| `String`  | `string`            | `eq`, `like`, `in`                   |
| `Integer` | `integer`           | `eq`, `gt`, `lt`, `gte`, `lte`, `in` |
| `Float`   | `numeric`           | `eq`, `gt`, `lt`, `gte`, `lte`, `in` |
| `Boolean` | `boolean`           | `eq`                                 |
| `Date`    | `date_format:Y-m-d` | `eq`, `gt`, `lt`, `gte`, `lte`       |

Les modes de comparaison sont des mots, jamais des symboles, pour ne jamais
entrer en collision avec les caractères de l'URL : `eq` (`=`), `gt` (`>`),
`gte` (`>=`), `lt` (`<`), `lte` (`<=`).

### Entier et flottant

```php
$filter->filter('views')->type(Type::Integer);
$filter->filter('rating')->type(Type::Float);
```

```sql
-- GET /articles?views[value]=10&views[mode]=gte
WHERE articles.views >= 10
```

```sql
-- GET /articles?rating[value]=4.5&rating[mode]=lt
WHERE articles.rating < 4.5
```

### `like`

`like` cherche la valeur n'importe où dans la colonne :

```sql
-- GET /articles?title[value]=fo&title[mode]=like
WHERE articles.title LIKE '%fo%' ESCAPE '!'
```

`%`, `_` et `!` saisis par le client sont comparés littéralement, jamais
comme des jokers :

```sql
-- GET /articles?title[value]=50%&title[mode]=like
WHERE articles.title LIKE '%50!%%' ESCAPE '!'
```

La recherche ignore la casse : `ILIKE` sur PostgreSQL ; sur MySQL, selon la
collation de la colonne.

### `in`

`in` prend une liste de valeurs, pour les filtres chaîne, entier et
flottant :

```sql
-- GET /articles?statut[value][]=draft&statut[value][]=published&statut[mode]=in
WHERE articles.status IN ('draft', 'published')
```

Chaque valeur est validée comme une valeur seule, `rules()` et `messages()`
du filtre compris, et convertie dans le type du filtre :

```sql
-- GET /articles?views[value][]=10&views[value][]=300&views[mode]=in
WHERE articles.views IN (10, 300)
```

Avec `not=1`, il devient `NOT IN`. En mode `in`, `value` doit être une liste
non vide ; dans les autres modes, une valeur seule.

### Booléen

```php
$filter->filter('featured')->type(Type::Boolean);
```

```sql
-- GET /articles?featured[value]=1
WHERE articles.featured = 1
```

Dans une query string, seuls `1` et `0` sont des booléens : `true` est
refusé.

```
GET /articles?featured[value]=true
```

```json
{
    "featured.value": [
        "The featured field must be true or false."
    ]
}
```

### Date

La valeur est un jour au format `Y-m-d`. La comparaison se fait au jour,
même sur une colonne date-heure, sans jamais entourer la colonne d'une
fonction SQL : son index reste utilisable.

```php
$filter->filter('published')->column('published_at')->type(Type::Date);
```

| Mode  | URL                                                | SQL                                                            |
|-------|----------------------------------------------------|----------------------------------------------------------------|
| `eq`  | `?published[value]=2024-01-15`                     | `published_at >= '2024-01-15' AND published_at < '2024-01-16'` |
| `gt`  | `?published[value]=2024-01-15&published[mode]=gt`  | `published_at >= '2024-01-16'`                                 |
| `gte` | `?published[value]=2024-01-15&published[mode]=gte` | `published_at >= '2024-01-15'`                                 |
| `lt`  | `?published[value]=2024-01-15&published[mode]=lt`  | `published_at < '2024-01-15'`                                  |
| `lte` | `?published[value]=2024-01-15&published[mode]=lte` | `published_at < '2024-01-16'`                                  |

```
GET /articles?published[value]=15/01/2024
```

```json
{
    "published.value": [
        "The published field must match the format Y-m-d."
    ]
}
```

### `null`

`nullable()` ajoute le mode `null` à un filtre de n'importe quel type, sans
valeur :

```php
$filter->filter('published')
    ->column('published_at')
    ->type(Type::Date)
    ->nullable();
```

```sql
-- GET /articles?published[mode]=null
WHERE articles.published_at IS NULL
```

```sql
-- GET /articles?published[mode]=null&published[not]=1
WHERE NOT (articles.published_at IS NULL)
```

En mode `null`, `value` est interdit. Sans `nullable()`, le mode `null` est
refusé comme n'importe quel mode inconnu.

## Inverser avec `not`

Il n'y a pas de mode `ne` : `not=1` inverse n'importe quel mode.

```sql
-- GET /articles?title[value]=Foo&title[not]=1
WHERE NOT (articles.title = 'Foo')
```

## Colonne, règles, messages et attribut

```php
$filter->filter('statut')               // nom public, dans l'URL
    ->column('status')                  // colonne réelle (défaut : le nom)
    ->type(Type::String)
    ->rules(['in:draft,published'])     // règles Laravel en plus du type
    ->messages(['in' => 'Statut invalide.']) // par nom de règle
    ->attribute('statut');              // nom affiché dans les erreurs
```

- `column()` garde le schéma de la base hors de l'URL.
- `rules()` ajoute des règles Laravel à la validation de `value`.
- `messages()` remplace le message d'une règle, par nom de règle.
- `attribute()` nomme le filtre dans les messages par défaut.

```
GET /articles?statut[value]=archived
```

```json
{
    "statut.value": [
        "Statut invalide."
    ]
}
```

## Erreurs de validation

| URL                                                 | Erreur                                                                                                           |
|-----------------------------------------------------|------------------------------------------------------------------------------------------------------------------|
| `?views[value]=abc`                                 | `views.value` : The views field must be an integer.                                                              |
| `?views[value]=10&views[mode]=like`                 | `views.mode` : The selected views is invalid.                                                                    |
| `?title[mode]=like`                                 | `title.value` : The title field is required when title is present.                                               |
| `?title=Foo`                                        | `title` : The title field must be an array.                                                                      |
| `?title[value][]=Foo`                               | `title.value` : The title field must be a string.                                                                |
| `?title[value]=Foo&title[mode]=in`                  | `title.value` : The title field must be an array.                                                                |
| `?views[value][]=abc&views[mode]=in`                | `views.value.0` : The views field must be an integer.                                                            |
| `?published[mode]=null&published[value]=2024-01-15` | `published.value` : The published field is prohibited.                                                           |
| `?title[mode]=null` (sans `nullable()`)             | `title.mode` : The selected title is invalid. `title.value` : The title field is required when title is present. |

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- filtre sans `type()` ;
- nom réservé (`with`, `sort`, `pagination`, `page`, `cursor`, `limit`),
  déclaré deux fois (y compris face à un scope), ou qui n'est pas fait que de
  lettres, chiffres et `_`.
