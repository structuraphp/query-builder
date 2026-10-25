# Scopes

[English](scopes.md) · **Français** · [Documentation](README.fr.md)

Un scope appelle un scope de requête du modèle, ou une méthode de son Builder
personnalisé, avec des arguments fournis par le client. Les arguments sont
validés et convertis d'après la signature PHP.

## D'où viennent les scopes

```php
class Article extends Model
{
    // scope classique
    public function scopePopular(Builder $query): void { … }

    // attribut Laravel (pas private)
    #[Scope]
    protected function minViews(Builder $query, int $min = 10): void { … }
}

// méthode d'un Builder personnalisé, déclaré avec newEloquentBuilder() ou
// #[UseEloquentBuilder(ArticleBuilder::class)]
class ArticleBuilder extends Builder
{
    public function viewsAtLeast(int $min): static { … }
}
```

```php
public function scope(ScopeInterface $scope): void
{
    $scope->scope('popular');
    $scope->scope('minViews');
    $scope->scope('viewsAtLeast');
}
```

- Comme dans Laravel, une méthode du Builder l'emporte sur un scope du
  modèle du même nom.
- Les `where` d'un scope du modèle sont regroupés par Laravel ; ceux d'une
  méthode du Builder ne le sont pas : encadrez ses conditions `OR` dans une
  closure `where()`.
- Les méthodes propres à Eloquent (`delete()`, `update()`…) ne sont jamais
  des scopes.
- `method()` donne au scope un nom public différent de la méthode :
  `$scope->scope('recent')->method('minViews')`.

## Scope sans argument

Il prend un booléen : `1` l'applique, `0` l'ignore.

```php
// scopePopular(Builder $query) : where views >= 100
$scope->scope('popular');
```

```sql
-- GET /articles?popular=1
WHERE views >= 100
```

```sql
-- GET /articles?popular=0
-- aucune condition
```

```
GET /articles?popular=yes
```

```json
{
    "popular": [
        "The popular field must be true or false."
    ]
}
```

## Scope avec arguments

Les arguments sont nommés comme les paramètres PHP, dans n'importe quel
ordre :

```php
// scopePublishedBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $to)
$scope->scope('publishedBetween');
```

```sql
-- GET /articles?publishedBetween[from]=2024-01-01&publishedBetween[to]=2024-12-31
WHERE published_at BETWEEN '2024-01-01' AND '2024-12-31'
```

Un argument sans valeur par défaut est obligatoire :

```
GET /articles?publishedBetween[from]=2024-01-01
```

```json
{
    "publishedBetween.to": [
        "The publishedBetween.to field is required when published between is present."
    ]
}
```

Un scope avec un argument obligatoire se demande comme un objet :
`?publishedBetween=1` est refusé. Un scope dont tous les arguments ont une
valeur par défaut accepte aussi un booléen (voir
[Valeurs par défaut](#valeurs-par-défaut)).

## Types

La validation et la conversion viennent du type PHP du paramètre :

| Type PHP                                | Règle               | Le scope reçoit               |
|-----------------------------------------|---------------------|-------------------------------|
| `int`                                   | `integer`           | `int`                         |
| `float`                                 | `numeric`           | `float`                       |
| `bool`                                  | `boolean`           | `bool`                        |
| `string`                                | `string`            | `string`                      |
| `array`                                 | `array`             | `array` (voir plus bas)       |
| enum backed                             | enum                | le cas de l'enum (`::from()`) |
| `CarbonImmutable`, `DateTimeInterface`… | `date_format:Y-m-d` | `CarbonImmutable`             |
| `Carbon`                                | `date_format:Y-m-d` | `Carbon`                      |

Tout autre type (non typé, `mixed`, union, autre classe) demande un
`cast()`.

### Enum

```php
// scopeOfStatus(Builder $query, Status $status)
$scope->scope('ofStatus');
```

```sql
-- GET /articles?ofStatus[status]=draft
WHERE status = 'draft'
```

```
GET /articles?ofStatus[status]=archived
```

```json
{
    "ofStatus.status": [
        "The selected ofStatus.status is invalid."
    ]
}
```

### Tableau

Sans `each()` ni `nested()`, un argument `array` est une liste plate : un
élément qui est lui-même un tableau est refusé. `each()` ajoute des règles à
chaque élément :

```php
// scopeOfStatuses(Builder $query, array $statuses)
$scope->scope('ofStatuses')
    ->rules(statuses: ['max:2'])                     // le tableau entier
    ->each(statuses: ['in:draft,published']);       // chaque élément
```

```sql
-- GET /articles?ofStatuses[statuses][]=draft&ofStatuses[statuses][]=published
WHERE status IN ('draft', 'published')
```

```
GET /articles?ofStatuses[statuses][]=archived
```

```json
{
    "ofStatuses.statuses.0": [
        "The selected ofStatuses.statuses.0 is invalid."
    ]
}
```

## Valeurs par défaut

Un argument avec une valeur par défaut devient facultatif. `default()` passe
avant la valeur par défaut PHP, et peut être une closure, évaluée à chaque
requête :

```php
// minViews(Builder $query, int $min = 10)
$scope->scope('recent')
    ->method('minViews')
    ->default(min: 50);                 // ou fn () => …
```

```sql
-- GET /articles?recent[min]=10
WHERE views >= 10
```

Quand tous les arguments ont une valeur par défaut, le scope accepte aussi
un booléen : `1` l'applique avec les valeurs par défaut, `0` l'ignore.

```sql
-- GET /articles?recent=1
WHERE views >= 50
```

Une date mutable (`now()`) donnée par défaut est convertie dans la classe
attendue par le scope.

## Options par argument

Toutes les options s'écrivent en **arguments nommés**, par nom de
paramètre :

```php
$scope->scope('recent')
    ->method('publishedAfter')
    ->default(date: fn () => now()->subWeek())
    ->rules(date: ['after:2020-01-01'])               // en plus de la règle du type
    ->messages(date: ['after' => 'Trop ancien.'])      // par nom de règle
    ->attribute(date: 'date de début');                // nom dans les erreurs
```

Sans `attribute()`, un argument s'appelle `scope.argument` dans les erreurs (`ofStatus.status`).

## Casts

`cast()` force la conversion d'un argument :

```php
// scopeNear(Builder $query, $lat, $lng) : paramètres non typés
$scope->scope('near')->cast(lat: Type::Float, lng: Type::Float);

// un enum backed
$scope->scope('ofStatus')->cast(status: Status::class);

// n'importe quelle closure
$scope->scope('priced')->cast(amount: fn (string $v) => Money::parse($v));
```

```sql
-- GET /articles?near[lat]=1.5&near[lng]=60
WHERE views BETWEEN 1.5 AND 60
```

Un cast par closure s'exécute une fois les règles de son argument passées,
et reçoit la valeur de la requête (chaînes, tableaux). S'il lève une
exception, le client reçoit une erreur de validation sur l'argument (`The selected near.lat is invalid.`).

## Structures avec `nested()`

`nested()` prend des règles Laravel par chemin pointé dans l'argument :
listes d'objets, objets, sous-tableaux, à n'importe quelle profondeur. Les
règles ne s'appliquent que si l'argument est fourni.

```php
// scopeInRanges(Builder $query, array $ranges)
$scope->scope('inRanges')
    ->nested(ranges: [
        '*' => ['array:min,max'],                   // chaque élément : {min, max}
        '*.min' => ['required', 'integer'],
        '*.max' => ['required', 'integer', 'gte:inRanges.ranges.*.min'],
    ])
    ->messages(ranges: ['*.min.required' => 'Min obligatoire.'])
    ->attribute(ranges: ['*.min' => 'minimum', '*.max' => 'maximum']);
```

```sql
-- GET /articles?inRanges[ranges][0][min]=1&inRanges[ranges][0][max]=10
--     &inRanges[ranges][1][min]=100&inRanges[ranges][1][max]=1000
WHERE
    (views BETWEEN '1' AND '10' OR views BETWEEN '100' AND '1000')
```

```
GET /articles?inRanges[ranges][0][min]=30&inRanges[ranges][0][max]=20
```

```json
{
    "inRanges.ranges.0.max": [
        "The maximum field must be greater than or equal to 30."
    ]
}
```

- Une règle qui cite un autre champ (`gte:`, `required_with:`…) utilise son
  chemin complet.
- `each(x: [...])` est un raccourci pour `nested(x: ['*' => [...]])`.
- `messages()` et `attribute()` prennent les mêmes chemins.
- Les valeurs restent des chaînes (`'1'`) : convertissez-les avec un cast par
  closure.

### Construire un objet

```php
// scopeViewsInRange(Builder $query, Range $range)
$scope->scope('viewsInRange')
    ->nested(range: [
        'min' => ['required', 'integer'],
        'max' => ['required', 'integer'],
    ])
    ->cast(range: fn (array $v) => new Range((int) $v['min'], (int) $v['max']));
```

```sql
-- GET /articles?viewsInRange[range][min]=10&viewsInRange[range][max]=100
WHERE views BETWEEN 10 AND 100
```

La closure ne s'exécute que sur une structure valide : si `min` manque, le
client ne reçoit que l'erreur de `nested()`.

## Erreurs de configuration

Une `ConfigurationException` est levée à la lecture de la query :

- scope qui n'existe ni sur le modèle ni sur son Builder ;
- nom réservé, déclaré deux fois (y compris face à un filtre), ou qui n'est
  pas fait que de lettres, chiffres et `_` ;
- option pour un argument que le scope n'a pas, ou passée sans nom ;
- paramètre ni typé ni casté, ou `cast()` qui n'est ni un `Type`, ni un enum
  backed, ni une closure.
