# OpenAPI

[English](openapi.md) · **Français** · [Documentation](README.fr.md)

[Scramble](https://scramble.dedoc.co) documente une `QueryRequest` comme
n'importe quelle form request : filtres, scopes, tris et pagination
deviennent des paramètres de query (`title[value]`, `sort[date]`…), les
arguments de contexte restent cachés.

## Les deux formes de la valeur d'un filtre

La valeur d'un filtre prend l'une de deux formes, selon son mode :

| Forme   | URL                                                    | Mode               |
|---------|--------------------------------------------------------|--------------------|
| Simple  | `?status[value]=active`                                | `eq` (défaut), `like`, `gt`… |
| Liste   | `?status[value][]=active&status[value][]=pending&status[mode]=in` | `in` |

La règle de `<nom>.value` dépend du mode, et Scramble ne sait pas la lire. Il
ne voit que la règle de chaque élément, `<nom>.value.*`, et documente la
liste :

```json
{"name": "status[value][]", "in": "query", "schema": {"type": "array", "items": {"type": "string"}}}
```

La forme simple, celle que la plupart des clients envoient, manque au
document. Un client généré à partir de lui (openapi-typescript par exemple)
n'a pas la clé `status[value]` et refuse `?status[value]=active`, que l'API
accepte.

## Documenter la forme simple

`ScalarFilterValueExtension` ajoute la forme simple à côté de la liste.
Enregistrez-la dans `config/scramble.php` :

```php
'extensions' => [
    StructuraPhp\QueryBuilder\Scramble\ScalarFilterValueExtension::class,
],
```

Pour chaque paramètre `<nom>[value][]` d'une opération qui a aussi
`<nom>[mode]`, elle ajoute juste avant lui un paramètre `<nom>[value]`, typé
comme un élément de la liste :

```json
{"name": "status[value]", "in": "query", "schema": {"type": "string"}}
{"name": "status[value][]", "in": "query", "schema": {"type": "array", "items": {"type": "string"}}}
```

- Elle retire aussi l'`enum` que Scramble pose sur le tableau lui-même :
  aucun tableau ne peut valoir une liste de chaînes.
- Un paramètre qui n'est pas un filtre est laissé tel quel : une liste sans
  `[mode]` à côté, ou qui n'est pas un tableau.
- La lancer deux fois ne change rien.

## Documenter les relations

`with` et `count` prennent une liste de relations déclarées par la query,
séparées par des virgules, vérifiée par une closure que Scramble ne peut pas
lire : il documente une simple `string`, et un client généré ne connaît ni
les noms ni le format.

`RelationParametersExtension` les lit dans la query de la requête que prend
l'action. Enregistrez-la à côté de l'autre :

```php
'extensions' => [
    StructuraPhp\QueryBuilder\Scramble\ScalarFilterValueExtension::class,
    StructuraPhp\QueryBuilder\Scramble\RelationParametersExtension::class,
    StructuraPhp\QueryBuilder\Scramble\NumberBoundsExtension::class,
    StructuraPhp\QueryBuilder\Scramble\PaginationParametersExtension::class,
],
```

```json
{
  "name": "with",
  "in": "query",
  "description": "Relations to load, separated by commas: `author`, `comments`.",
  "schema": {"type": "string", "pattern": "^\\s*(author|comments)\\s*(,\\s*(author|comments)\\s*)*$"}
}
```

- Une query qui ne déclare aucune relation (ou aucun comptage) interdit le
  paramètre : il est retiré de l'opération.
- Le motif accepte les espaces autour des noms, comme le serveur.
- Une action sans paramètre `QueryRequest` est laissée telle quelle.

## Retirer les bornes du serveur

Deux bornes ne servent qu'à protéger le serveur, et font du bruit dans le
document :

- un float est `between` ±1.797e308, le plus grand float : sa règle doit être
  lisible par Scramble, qui en fait un `minimum` et un `maximum` ;
- `page` est plafonnée à `PHP_INT_MAX / maxLimit` (`92233720368547760` pour
  une limite de 100), pour que le décalage de Laravel reste un entier.

Un client généré depuis le document les arrondit, ou les refuse.
`NumberBoundsExtension` retire tout `minimum` ou `maximum`, sur un nombre ou
sur les éléments d'une liste, qu'un nombre JSON ne peut pas représenter
exactement (au-delà de 2^53 - 1). Les autres bornes restent : `limit`
garde son `maximum: 100`.

```json
{"name": "rating[value][]", "schema": {"type": "array", "items": {"type": "number"}}}
{"name": "page", "schema": {"type": "integer", "minimum": 1}}
```

## Documenter la pagination

Scramble lit les règles de `pagination`, `limit`, `page` et `cursor`, mais
pas ce que la query configure autour.
`PaginationParametersExtension` ajoute :

- le défaut de `pagination` (`default()`) et de `limit` (`limit()`), et `1`
  pour `page` ;
- une description de chacun : `page` n'est lue que par la pagination
  `offset`, et `cursor` que par la pagination `cursor`.

```json
{"name": "pagination", "description": "How the results are paginated.", "schema": {"type": "string", "enum": ["offset", "cursor"], "default": "offset"}}
{"name": "limit", "description": "Number of results per page.", "schema": {"type": "integer", "default": 50, "minimum": 1, "maximum": 100}}
```

Un paramètre qu'aucun mode autorisé ne lit est retiré : `page` sans
`offset`, `cursor` sans `cursor`, et `limit` aussi quand seul `none` est
autorisé.

`dedoc/scramble` n'est pas une dépendance de la librairie : l'extension ne
fonctionne que là où Scramble est installé.
