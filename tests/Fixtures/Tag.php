<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
