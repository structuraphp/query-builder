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
| `mode`  | non (`=`)   | Opérateur, selon le type du filtre |
| `not`   | non         | `1` inverse la condition           |

```sql
-- GET /articles?title[value]=Foo
WHERE articles.title = 'Foo'
```

Un filtre absent de l'URL n'est pas appliqué.

## Types et modes

Le type est obligatoire. Il fixe la validation de `value` et les modes
acceptés :

| `Type`    | Validation          | Modes                     |
|-----------|---------------------|---------------------------|
| `String`  | `string`            | `=`, `like`               |
| `Integer` | `integer`           | `=`, `>`, `<`, `>=`, `<=` |
| `Float`   | `numeric`           | `=`, `>`, `<`, `>=`, `<=` |
| `Boolean` | `boolean`           | `=`                       |
| `Date`    | `date_format:Y-m-d` | `=`, `>`, `<`, `>=`, `<=` |

### Entier et flottant

```php
$filter->filter('views')->type(Type::Integer);
$filter->filter('rating')->type(Type::Float);
```

```sql
-- GET /articles?views[value]=10&views[mode]=>=
WHERE articles.views >= 10
```

```sql
-- GET /articles?rating[value]=4.5&rating[mode]=<
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
{"featured.value": ["The featured field must be true or false."]}
```

### Date

La valeur est un jour au format `Y-m-d`. La comparaison se fait au jour,
même sur une colonne date-heure, sans jamais entourer la colonne d'une
fonction SQL : son index reste utilisable.

```php
$filter->filter('published')->column('published_at')->type(Type::Date);
```

| Mode | URL                                               | SQL                                                            |
|------|---------------------------------------------------|----------------------------------------------------------------|
| `=`  | `?published[value]=2024-01-15`                    | `published_at >= '2024-01-15' AND published_at < '2024-01-16'` |
| `>`  | `?published[value]=2024-01-15&published[mode]=>`  | `published_at >= '2024-01-16'`                                 |
| `>=` | `?published[value]=2024-01-15&published[mode]=>=` | `published_at >= '2024-01-15'`                                 |
| `<`  | `?published[value]=2024-01-15&published[mode]=<`  | `published_at < '2024-01-15'`                                  |
| `<=` | `?published[value]=2024-01-15&published[mode]=<=` | `published_at < '2024-01-16'`                                  |

```
GET /articles?published[value]=15/01/2024
```

```json
{"published.value": ["The published field must match the format Y-m-d."]}
```

## Inverser avec `not`

Il n'y a pas de mode `!=` : `not=1` inverse n'importe quel mode.

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

| URL                                 | Erreur                                                             |
|-------------------------------------|--------------------------------------------------------------------|
| `?views[value]=abc`                 | `views.value` : The views field must be an integer.                |
| `?views[value]=10&views[mode]=like` | `views.mode` : The selected views is invalid.                      |
| `?title[mode]=like`                 | `title.value` : The title field is required when title is present. |
| `?title=Foo`                        | `title` : The title field must be an array.                        |
| `?title[value][]=Foo`               | `title.value` : The title field must be a string.                  |

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- filtre sans `type()` ;
- nom réservé (`with`, `sort`, `pagination`, `page`, `cursor`, `limit`),
  déclaré deux fois (y compris face à un scope), ou qui n'est pas fait que de
  lettres, chiffres et `_`.
