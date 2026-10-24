<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use Illuminate\Database\Eloquent\Model;

interface FromModel
{
    /**
     * @return class-string<Model>
     */
    public function getModel(): string;
}
