<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan\Data;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ScopeRangeBuilder;
use Tests\StructuraPhp\QueryBuilder\Fixtures\SecretViewsArticle;

#[Hidden(['secret', 'token'])]
class HiddenArrayArticle extends SecretViewsArticle {}

#[Hidden('secret', 'token')]
trait HidesSecrets {}

class HiddenTraitArticle extends Model
{
    use HidesSecrets;
}

#[UseEloquentBuilder(builderClass: ScopeRangeBuilder::class)]
class NamedBuilderArticle extends Model {}

class MissingBuilderArticle extends Model
{
    protected static string $builder = 'Tests\Missing\Builder';
}

class UnsetHiddenArticle extends Model
{
    protected $hidden;
}

class StringHiddenArticle extends Model
{
    protected $hidden = 'secret';
}

class MixedHiddenArticle extends Model
{
    protected $hidden = [null, 'secret', 1];
}
