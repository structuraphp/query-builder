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

Les listes de votre API ont besoin de filtres, de tris, de relations et de
pagination. Les écrire à la main dans chaque contrôleur est répétitif ;
laisser le client piloter la requête est risqué. Query Builder vous donne
les deux : le client compose sa requête depuis l'URL, et **vous déclarez
exactement ce qu'il a le droit de faire**, dans une seule classe.

```sql
-- GET /articles?title[value]=laravel&title[mode]=like&popular=1&sort[date]=desc&with=author
SELECT * FROM articles
WHERE articles.title LIKE '%laravel%' ESCAPE '!' AND views >= 100
ORDER BY articles.created_at DESC LIMIT 25 OFFSET 0
SELECT id, name FROM authors WHERE authors.id IN (…)
```

Ce qui n'est pas déclaré est refusé : le client ne peut jamais filtrer,
trier ou charger une colonne sensible (`password`). Une entrée invalide
donne une erreur de validation Laravel (422), jamais une 500.

## Prérequis

| Version de PHP | Query Builder 0.x |
|----------------|-------------------|
| <= 8.3         | ✗ Non supportée   |
| 8.4 / 8.5      | ✓ Supportée       |

- Laravel 13
- MySQL, PostgreSQL ou SQLite

## Démarrage rapide

### Installation

```shell
composer require structuraphp/query-builder
```

### 1. Déclarer la query

```php
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
    }

    public function scope(ScopeInterface $scope): void
    {
        $scope->scope('popular');               // Article::scopePopular()
    }

    public function sort(SortInterface $sort): void
    {
        $sort->sort('date')->column('created_at');
        $sort->default('date', Direction::Desc);
    }

    public function relation(RelationInterface $relation): void
    {
        $relation->relation('author')->fields(['id', 'name']);
    }

    public function paginate(PaginationInterface $pagination): void
    {
        $pagination->limit(25)->maxLimit(100);
    }
}
```

### 2. Créer la request

```php
final class ArticleQueryRequest extends QueryRequest
{
    protected function getQuery(): string
    {
        return ArticleQuery::class;
    }
}
```

Les règles de validation sont générées à partir de la déclaration.

### 3. L'utiliser dans un contrôleur

```php
public function index(ArticleQueryRequest $request)
{
    $parameters = $request->parameters();
    $query = $parameters->builder();    // un Builder Eloquent, toujours à vous
    $query->where('tenant_id', $request->user()->tenant_id);

    return $parameters->paginate($query);
}
```

C'est tout : la requête ci-dessus renvoie une liste d'articles paginée,
filtrée et triée, avec leurs auteurs.

## Fonctionnalités

- **🔒 Déclaré, jamais deviné.** Le client n'utilise que ce que la classe de
  query déclare. Les noms publics restent séparés du schéma (`column()`), et
  les colonnes chargées d'une relation sont fixées par vous (`fields()`).
- **✅ Validation Laravel.** Règles, messages et attributs sont générés : le
  client reçoit des erreurs 422 champ par champ. Ajoutez vos propres règles
  et messages, et validez aussi hors HTTP (`QueryParameters::fromArray()`).
- **🔎 Filtres typés.** Filtres chaîne, entier, flottant, booléen et date
  avec leurs opérateurs ; recherches `like` échappées et insensibles à la
  casse ; comparaisons de dates au jour qui gardent vos index utilisables.
- **🧩 Des scopes avec de vrais arguments.** Appelez les scopes du modèle,
  les méthodes `#[Scope]` ou celles de votre Builder personnalisé. Les
  arguments sont validés et convertis d'après la signature PHP : enums,
  dates, tableaux, structures imbriquées, ou vos propres objets via un cast.
- **🔐 Contexte côté serveur.** Fournissez des arguments de scope depuis le
  code (l'utilisateur courant, des identifiants calculés par un service),
  jamais depuis le client, et calculez-les seulement si le scope est
  demandé.
- **↕️ Tri.** Tris déclarés sur des colonnes ou des nombres de relations,
  priorité donnée par l'URL, tris par défaut.
- **🔗 Relations.** Le client choisit les relations à charger ou à compter,
  parmi celles déclarées.
- **📄 Pagination.** Par offset, par curseur (stable, sans requête de
  comptage) ou sans pagination, avec une limite que le client ne peut pas
  dépasser.
- **🧱 Pensé pour le domaine.** `QueryParameters` est un value object sans
  dépendance HTTP : passez-le à un service, et continuez d'affiner le
  Builder.
- **📘 Prêt pour OpenAPI.** [Scramble](https://scramble.dedoc.co) documente
  vos query requests comme n'importe quelle form request.

## 📖 Documentation

Le [guide d'utilisation](docs/README.fr.md) détaille chaque fonctionnalité
avec sa configuration, des exemples d'URL, et le SQL ou l'erreur de
validation qu'elles produisent : [filtres](docs/filters.fr.md),
[scopes](docs/scopes.fr.md), [contexte](docs/context.fr.md),
[tri](docs/sorting.fr.md), [relations](docs/relations.fr.md) et
[pagination](docs/pagination.fr.md).

## Licence

Query Builder est un logiciel libre sous [licence MIT](LICENSE).
