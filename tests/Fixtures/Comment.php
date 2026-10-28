<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
