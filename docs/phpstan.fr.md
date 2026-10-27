# Règles PHPStan

[English](phpstan.md) · **Français** · [Documentation](README.fr.md)

Une classe de query n'est vérifiée qu'à l'appel de l'endpoint : un scope qui
n'existe plus sur le modèle répond 500. Les règles PHPStan livrées avec la
librairie signalent ces erreurs dans l'éditeur et en CI.

Les règles ne font que lire le code : elles n'exécutent jamais vos modèles,
vos classes de query ni le conteneur. Larastan n'est pas requis.

## Installation

Avec [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer),
les règles sont chargées automatiquement. Sinon, incluez-les dans votre
`phpstan.neon` :

```neon
includes:
    - vendor/structuraphp/query-builder/extension.neon
```

## Règles

Chaque erreur porte un identifiant, pour ignorer une règle seule.

### `structuraQueryBuilder.scopeNotFound`

Un scope déclaré ne correspond ni à un scope du modèle, ni à une méthode de
son Builder :

```php
public function scope(ScopeInterface $scope): void
{
    $scope->scope('published'); // Scope "published" does not exist on App\Models\Article.
}
```

Un modèle dont le Builder ne peut pas être déterminé statiquement (type de
retour de `newEloquentBuilder()` non final, ou `newModelQuery()`,
`newQuery()` ou `resolveCustomBuilderClass()` redéfinis) n'est pas vérifié
pour les scopes.

### `structuraQueryBuilder.configuration`

Toutes les autres erreurs que la librairie lève à l'exécution, avec le même
message : noms en double ou réservés, filtre sans type, relation inconnue,
relation sans son parent, tri par défaut non déclaré, tri sur une colonne
cachée en pagination par curseur, arguments de scope invalides (argument
inconnu, cast manquant, argument fourni par le contexte)…

```php
public function filter(FilterInterface $filter): void
{
    $filter->filter('title');       // Filter "title" has no type.
    $filter->filter('page')->type(Type::String); // "page" is a reserved query parameter.
}
```

### `structuraQueryBuilder.flatConfiguration`

Une méthode de configuration doit être une suite d'appels à plat : ni
condition, ni méthode d'aide, ni appel à `parent::`, pour que les deux règles
précédentes voient toute la configuration.

```php
public function filter(FilterInterface $filter): void
{
    if ($this->admin) { // Query configuration must be a flat list of calls:
        $filter->filter('internal')->type(Type::String); // no condition, helper or parent call.
    }
}
```

Les classes de query configurées par des closures injectées sont aussi
signalées : ignorez cet identifiant pour elles.

## Chaînes littérales

Chaque nom de la configuration (filtres, scopes, tris, relations, arguments)
est typé `literal-string` : PHPStan signale un nom construit depuis une
variable ou une saisie. Un `literal-string` à plusieurs valeurs possibles est
accepté, mais les vérifications qui en dépendent sont ignorées.

PHPStan ne signale une violation de `literal-string` qu'à partir du niveau 7.
En dessous, un nom dynamique passe, et les règles `scopeNotFound` et
`configuration` l'ignorent sans rien dire.

### Mise à jour

Au niveau 7 ou plus, un projet qui passe des noms non littéraux reçoit de
nouvelles erreurs `argument.type` : construisez le nom à partir de littéraux,
ou ignorez l'erreur là où la valeur est sûre.

## Configuration héritée

Une méthode de configuration héritée d'une classe parente est vérifiée pour
chaque classe de query concrète qui l'utilise. Les erreurs sont signalées
dans le fichier du parent.

## Ce qui est ignoré

Les règles ne signalent jamais ce qu'elles ne savent pas lire : un nom qui
n'est pas une chaîne constante, un modèle qu'elles ne résolvent pas, une
classe pas encore analysée, une classe de query anonyme (sa réflexion n'existe
que dans le processus qui l'a trouvée). Sur une analyse partielle (l'éditeur
analyse un seul fichier), la règle qui a besoin de tout le projet ne signale
rien.

## Ignorer un identifiant

```neon
parameters:
    ignoreErrors:
        -
            identifier: structuraQueryBuilder.flatConfiguration
            path: tests/*
```
