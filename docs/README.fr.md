# Documentation

[English](README.md) · **Français**

Ce guide détaille chaque fonctionnalité avec sa configuration, les URL
qu'envoie le client et ce qu'elles produisent : la requête SQL, ou l'erreur
de validation (422).

- [Filtres](filters.fr.md) : comparer une colonne à une valeur.
- [Scopes](scopes.fr.md) : appeler un scope du modèle, ou une méthode de son
  Builder, avec des arguments validés.
- [Contexte](context.fr.md) : fournir des arguments de scope depuis le code,
  jamais depuis le client.
- [Tri](sorting.fr.md) : ordonner les résultats.
- [Relations](relations.fr.md) : charger des relations.
- [Pagination](pagination.fr.md) : par offset, par curseur ou sans
  pagination.

Pour l'installation et le démarrage rapide, voir le
[README](../README.fr.md).

## Requests et paramètres

`QueryRequest` ne fait que valider. `parameters()` renvoie un
`QueryParameters`, un value object sans dépendance HTTP, que peut recevoir
un service de domaine :

```php
// Contrôleur
public function index(ArticleQueryRequest $request, ArticleService $service)
{
    return $service->list($request->parameters());
}

// Service de domaine
public function list(QueryParameters $parameters)
{
    $query = $parameters->builder(); // filtres, scopes, tris et relations
    $query->where('tenant_id', $this->tenant->id);

    return $parameters->paginate($query);
}
```

- `builder()` renvoie un `Builder` Eloquent encore modifiable. Il prend le
  [contexte](context.fr.md) des scopes.
- `paginate()` renvoie un `LengthAwarePaginator` (`offset`), un
  `CursorPaginator` (`cursor`) ou une `Collection` (`none`).
- La classe de query est résolue par le container Laravel : son
  constructeur peut recevoir des dépendances.
- Les méthodes `messages()` et `attributes()` de la request restent
  disponibles et passent avant celles générées.

Depuis une commande, un job ou un test, `fromArray()` applique la même
validation et lève une `ValidationException` si les données sont
invalides :

```php
$parameters = QueryParameters::fromArray(app(ArticleQuery::class), [
    'title' => ['value' => 'laravel', 'mode' => 'like'],
    'limit' => 20,
]);

$page = $parameters->paginate($parameters->builder());
```

[Scramble](https://scramble.dedoc.co) documente une `QueryRequest` comme
n'importe quelle form request : filtres, scopes, tris et pagination
deviennent des paramètres de query (`title[value]`, `sort[date]`…), les
arguments de contexte restent cachés.

## Conventions

Les exemples utilisent un modèle `Article`, avec un `Author` et des
`Comment` :

| Colonne        | Type       |
|----------------|------------|
| `id`           | entier     |
| `author_id`    | entier     |
| `title`        | chaîne     |
| `status`       | chaîne     |
| `views`        | entier     |
| `rating`       | flottant   |
| `featured`     | booléen    |
| `published_at` | date       |
| `created_at`   | date-heure |

Chaque exemple ne déclare que la section dont il parle, dans une classe de
query :

```php
final class ArticleQuery implements FromModel, WithFilter
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        // la configuration de l'exemple
    }
}
```

- Les URL sont montrées décodées pour la lisibilité : un client envoie
  `?title%5Bvalue%5D=Foo` pour `?title[value]=Foo`. Des bibliothèques comme
  [qs](https://github.com/ljharb/qs) les construisent à partir d'un objet.
- Le SQL est montré tel que SQLite l'exécute, simplifié (`articles.title`
  pour `"articles"."title"`).
- Les messages d'erreur sont ceux de Laravel en anglais : ils suivent la
  langue de l'application.
- Les clés que la query ne déclare pas (`?utm_source=…`) sont ignorées.

## Limites connues

- Pas de filtre sur une colonne de relation (`author.name`), pas de groupes
  `OR` entre filtres, pas de mode `in` : passez par un scope.
- Pas d'alias ni de contrainte sur les relations chargées (voir
  [Relations](relations.fr.md)).
- Les scopes définis en macro du Builder ne sont pas supportés.
- Une valeur entière hors de la plage de la colonne (`int4` PostgreSQL)
  n'est pas détectée : ajoutez une règle `max:` au filtre.
