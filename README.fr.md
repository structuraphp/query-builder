<div align="center" style="display:grid;place-items:center;">
    <h1>Query Builder</h1>
    <p>Transforme la query string d'une requête HTTP en requête Eloquent sûre et déclarative.</p>

[![License](https://img.shields.io/github/license/structuraphp/query-builder.svg)](https://github.com/structuraphp/query-builder/blob/main/LICENSE "LICENSE")
[![PHP from Packagist](https://img.shields.io/badge/PHP-%3E%3D8.4-%238892bf)](#prérequis "PHP version 8.4 minimum")
[![Laravel](https://img.shields.io/badge/Laravel-13-%23ff2d20)](#prérequis "Laravel 13")
[![Packagist Downloads](https://img.shields.io/packagist/dm/structuraphp/query-builder)](https://packagist.org/packages/structuraphp/query-builder "packagist downloads")

[English](README.md) · **Français**
</div>

## À propos

Transforme la query string d'une requête HTTP en requête Eloquent :
filtres, scopes, tri, relations et pagination.

Tout ce que le client peut faire est **déclaré** dans une classe de query.
Ce qui n'est pas déclaré est refusé : le client ne peut jamais filtrer, trier
ou charger une colonne ou une relation sensible (`password`, etc.). Une entrée
invalide donne une erreur de validation Laravel (422 en JSON), jamais une 500.

## Démarrage rapide

### Installation

```shell
composer require structuraphp/query-builder
```

### 1. Déclarer la query

Une classe de query implémente `FromModel`, puis une interface `With*` par
fonctionnalité utilisée. Chaque interface est facultative.

```php
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationInterface;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\Contract\Query\WithFilter;
use StructuraPhp\QueryBuilder\Contract\Query\WithPaginate;
use StructuraPhp\QueryBuilder\Contract\Query\WithRelation;
use StructuraPhp\QueryBuilder\Contract\Query\WithScope;
use StructuraPhp\QueryBuilder\Contract\Query\WithSort;
use StructuraPhp\QueryBuilder\Enum\Type;

final class ArticleQuery implements
    FromModel,
    WithFilter,
    WithScope,
    WithSort,
    WithRelation,
    WithPaginate
{
    public function getModel(): string
    {
        return Article::class;
    }

    public function filter(FilterInterface $filter): void
    {
        $filter->filter('title')->type(Type::String);
        $filter->filter('views')->type(Type::Integer);
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');
        $scope->scope('publishedBetween');
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->sort('title');
        $sort->default('date', 'desc');
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author')->fields(['id', 'name']);
        $relation->relation('comments');
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->default('cursor')->limit(25)->maxLimit(100);
    }
}
```

La classe est instanciée par le container Laravel : elle peut recevoir des
dépendances dans son constructeur.

### 2. Créer la request

```php
use StructuraPhp\QueryBuilder\Service\QueryRequest;

final class ArticleQueryRequest extends QueryRequest
{
    protected function getQuery(): string
    {
        return ArticleQuery::class;
    }
}
```

Les règles de validation, les messages et les attributs sont générés à partir
de la déclaration. Les méthodes `messages()` et `attributes()` de la request
restent disponibles et passent avant celles de la config.

### 3. Utiliser les paramètres

La request ne fait que valider. `parameters()` renvoie un `QueryParameters`,
un value object sans dépendance HTTP, que l'on peut passer à un service de
domaine :

```php
// Contrôleur
public function index(
    ArticleQueryRequest $request,
    ArticleService $service,
) {
    return $service->list($request->parameters());
}

// Service de domaine
public function list(QueryParameters $parameters)
{
    $query = $parameters->builder(); // filtres, scopes, tri et relations
    $query->where('tenant_id', $this->tenant->id);

    return $parameters->paginate($query);
}
```

`builder()` renvoie un `Builder` Eloquent encore modifiable. `paginate()`
renvoie un `LengthAwarePaginator` (mode `offset`), un `CursorPaginator`
(mode `cursor`) ou une `Collection` (mode `none`).

### Sans requête HTTP

Depuis une commande, un job ou un test, `fromArray()` applique la même
validation et lève une `ValidationException` si les données sont invalides :

```php
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;

$parameters = QueryParameters::fromArray(app(ArticleQuery::class), [
    'title' => ['value' => 'laravel', 'mode' => 'like'],
    'limit' => 20,
]);

$page = $parameters->paginate($parameters->builder());
```

## Prérequis

| Version de PHP | Query Builder 0.x |
|----------------|-------------------|
| <= 8.3         | ✗ Non supportée   |
| 8.4 / 8.5      | ✓ Supportée       |

- Laravel 13
- Moteurs supportés : MySQL, PostgreSQL, SQLite

## Avec Scramble

[Scramble](https://scramble.dedoc.co) documente une `QueryRequest` comme
n'importe quelle form request : filtres, scopes, tris et pagination
deviennent des paramètres de query (`title[value]`, `sort[date]`…). Les
arguments de contexte restent cachés.

## Format de l'URL

### Filtres

Le nom du filtre est à la racine de la query string :

```
?title[value]=foo&title[mode]=like&title[not]=1
    → WHERE title NOT LIKE '%foo%'

?views[value]=100&views[mode]=>
    → WHERE views > 100
```

| Clé     | Obligatoire | Rôle                                      |
|---------|-------------|-------------------------------------------|
| `value` | oui         | Valeur comparée                           |
| `mode`  | non (`=`)   | Opérateur, selon le type du filtre        |
| `not`   | non         | Booléen : inverse la condition            |

Le type du filtre fixe la validation de la valeur et les modes acceptés :

| `Type`    | Validation          | Modes                       |
|-----------|---------------------|-----------------------------|
| `String`  | `string`            | `=`, `like`                 |
| `Integer` | `integer`           | `=`, `>`, `<`, `>=`, `<=`   |
| `Float`   | `numeric`           | `=`, `>`, `<`, `>=`, `<=`   |
| `Boolean` | `boolean`           | `=`                         |
| `Date`    | `date_format:Y-m-d` | `=`, `>`, `<`, `>=`, `<=`   |

- `like` entoure la valeur de `%…%` et compare `%`, `_` et `!`
  littéralement. Il ignore la casse (`ilike` sur PostgreSQL ; sur MySQL,
  selon la collation de la colonne).
- `Date` compare au jour, même sur une colonne date-heure, sans fonction
  SQL sur la colonne : `=` devient `col >= jour AND col < lendemain`,
  l'index reste utilisable.
- Pour exclure, on ajoute `not=1` (`?title[value]=foo&title[not]=1`) : il
  n'y a pas de mode `!=`.

### Scopes

Les scopes sont aussi à la racine. Leurs arguments sont nommés comme les
paramètres PHP du scope, dans n'importe quel ordre :

```
?popular=1
    → ->popular()

?publishedBetween[from]=2024-01-01&publishedBetween[to]=2024-12-31
    → ->publishedBetween(from: …, to: …)

?ofTags[tags][]=php&ofTags[tags][]=laravel
    → ->ofTags(['php', 'laravel'])
```

- Un scope sans argument prend un booléen et n'est appliqué que s'il est vrai.
- Un scope absent de l'URL n'est jamais appliqué.
- Un argument absent de l'URL prend la valeur par défaut de la config, puis
  celle de la signature PHP. Sans valeur par défaut, il est obligatoire.

### Tri

```
?sort[date]=desc&sort[title]=asc
    → ORDER BY created_at DESC, title ASC
```

L'ordre des clés donne la priorité. Sans `sort`, le tri par défaut de la
config s'applique. Dès que le client envoie `sort`, il le remplace
entièrement.

### Relations

```
?with=author,comments
```

Chaque relation doit être déclarée. Les colonnes chargées sont fixées par la
config (`fields()`), jamais par le client.

### Pagination

| Paramètre    | Défaut    | Rôle                                         |
|--------------|-----------|----------------------------------------------|
| `pagination` | `offset`  | Mode : `offset`, `cursor` ou `none`          |
| `page`       | `1`       | Numéro de page (mode `offset`)               |
| `cursor`     | —         | Curseur (mode `cursor`)                      |
| `limit`      | `50`      | Éléments par page, borné par `maxLimit`      |

Par défaut, seuls `offset` et `cursor` sont autorisés, avec un `maxLimit` de
100. Le mode `none` doit être autorisé explicitement avec `allow()`.

### Clés réservées

`with`, `sort`, `pagination`, `page`, `cursor` et `limit` ne peuvent pas être
utilisées comme nom de filtre ou de scope. Les autres clés inconnues
(`utm_source`…) sont ignorées.

## Configuration

### Filtres

```php
$filter->filter('statut')               // nom public, dans l'URL
    ->column('status')                  // colonne réelle (défaut : le nom)
    ->type(Type::String)                // obligatoire
    ->rules(['in:draft,published'])     // règles Laravel en plus du type
    ->messages(['in' => 'Statut invalide.'])
    ->attribute('statut');              // nom affiché dans les erreurs
```

### Scopes

Le scope est un `scopeXxx()` ou une méthode `#[Scope]` du modèle. Les
arguments sont convertis d'après la signature : `int`, `float`, `bool`,
`string`, `array`, enum backed (`::from()`), `CarbonImmutable` ou `Carbon`
(format `Y-m-d`).

Toutes les options s'écrivent en **arguments nommés**, par nom de paramètre :

```php
$scope->scope('recent')                 // nom public
    ->method('publishedAfter')          // méthode réelle (défaut : le nom)
    ->default(date: fn () => now()->subWeek())
    ->rules(date: ['after:2020-01-01'])
    ->messages(date: ['after' => 'Trop ancien.'])
    ->attribute(date: 'date de début');

$scope->scope('ofTags')
    ->rules(tags: ['max:10'])           // le tableau entier
    ->each(tags: ['string']);           // chaque élément

$scope->scope('inRanges')               // toute structure, comme Laravel
    ->nested(ranges: [
        '*' => ['array:min,max'],
        '*.min' => ['required', 'integer'],
        '*.max' => ['required', 'integer', 'gte:inRanges.ranges.*.min'],
    ])
    ->messages(ranges: ['*.min.required' => 'Min obligatoire.']);

$scope->scope('near')                   // paramètres non typés
    ->cast(lat: Type::Float, lng: Type::Float);

$scope->scope('ofStatus')
    ->cast(status: Status::class);      // enum backed

$scope->scope('priced')
    ->cast(amount: fn (string $v) => Money::parse($v));
```

- `nested()` prend des règles Laravel par chemin pointé dans l'argument
  (`*.min`, `geo.lat`, `tags.*`), appliquées seulement si l'argument est
  fourni. Une règle qui cite un autre champ (`gte:`, `required_with:`…)
  utilise son chemin complet. `each(x: [...])` est un raccourci pour
  `nested(x: ['*' => [...]])`.
- Un argument `array` sans `each()` ni `nested()` est une liste plate : un
  élément qui est lui-même un tableau est rejeté.
- `cast()` est obligatoire pour un paramètre non typé, `mixed`, union, enum
  non backed ou de toute autre classe.
- Une closure de `cast()` qui lève une exception donne une erreur de
  validation.
- Une valeur de `default()` peut être fixe ou une closure. Une date mutable
  (`now()`) est convertie dans la classe attendue par le scope.

### Contexte

Certains arguments de scope doivent venir du code, jamais du client :
l'utilisateur courant, des identifiants calculés par un service… On les
déclare avec `context()` :

```php
// scopeForUser(Builder $query, string $search, User $user, array $groupIds)
$scope->scope('forUser')->context('user', 'groupIds');
```

L'URL ne porte que `search` (`?forUser[search]=foo`) : une clé
`forUser[user]` envoyée par le client est ignorée. Le code fournit le
contexte, par nom de scope, au moment de construire la requête :

```php
$query = $parameters->builder(context: [
    'forUser' => [
        'user' => $user,
        'groupIds' => fn () => $groups->idsFor($user),
    ],
]);
```

- Une closure n'est appelée que si le client a demandé le scope, sauf si le
  paramètre est lui-même typé `Closure` ou `callable`.
- La closure reçoit, par nom, les arguments du scope venant de l'URL, une
  fois castés et complétés par leur valeur par défaut (un enum, une date…) :
  `'groupIds' => fn (string $search) => $groups->idsMatching($user, $search)`.
  Un paramètre de la closure qui n'en fait pas partie, et n'a pas de valeur
  par défaut, lève une `InvalidArgumentException`.
- Un scope dont tous les arguments viennent du contexte est un interrupteur
  booléen : `?mine=1` pour `scopeMine(Builder $query, User $user)`.
- `default()` ou la valeur par défaut PHP remplace un contexte manquant ;
  sinon, l'application du scope lève une `LogicException`.
- Une clé de contexte pour un scope non déclaré, ou pour un argument non
  déclaré avec `context()`, lève une `InvalidArgumentException`.

### Tri

```php
$sort->sort('date')->column('created_at');
$sort->sort('title');
$sort->default('date', 'desc')->default('title', 'asc');
```

En mode `cursor`, la clé primaire est ajoutée en dernier critère de tri pour
que les pages restent stables.

### Relations

```php
$relation->relation('author')->fields(['id', 'name']);
$relation->relation('author.profile');  // relation imbriquée
```

Pensez à inclure dans `fields()` les clés nécessaires à la relation.

### Pagination

```php
$pagination
    ->default('cursor')                 // mode par défaut
    ->limit(25)                         // éléments par page par défaut
    ->maxLimit(100)                     // limite acceptée
    ->allow('offset', 'cursor', 'none');
```

## Erreurs de configuration

Une config incohérente lève une
`StructuraPhp\QueryBuilder\Exception\ConfigurationException` au moment où la
query est lue, avant la validation des données du client :

- nom invalide (lettres, chiffres et `_` uniquement, sans chiffre en tête),
  réservé ou déclaré deux fois ;
- filtre sans `type()` ;
- scope ou relation inexistants sur le modèle ;
- option de scope pour un argument qui n'existe pas, ou passée sans nom ;
- `cast()`, `rules()`, `each()`, `nested()`, `messages()` ou `attribute()`
  sur un argument de contexte ;
- paramètre de scope ni typé ni casté, ou `cast()` qui n'est ni un `Type`,
  ni un enum backed, ni une closure ;
- tri par défaut non déclaré ou direction autre que `asc`/`desc` ;
- mode de pagination inconnu, mode par défaut non autorisé, `limit` hors
  de `1…maxLimit`.

## Limites connues

- Pas d'alias pour les relations.
- Pas de filtre sur une colonne de relation (`author.name`), pas de groupes
  `OR`, pas de mode `in` : passez par un scope.
- Les scopes définis sur un Builder personnalisé ou en macro ne sont pas
  supportés.
- Une valeur entière hors de la plage de la colonne (`int4` PostgreSQL) n'est
  pas détectée : ajoutez une règle `max:` au filtre.
