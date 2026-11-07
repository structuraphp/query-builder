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

`dedoc/scramble` n'est pas une dépendance de la librairie : l'extension ne
fonctionne que là où Scramble est installé.
