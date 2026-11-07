<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The builder of the model, for builder() to return it.
 *
 * @template-covariant TBuilder of Builder<covariant Model> = Builder<Model>
 */
interface FromModel
{
    /**
     * @return class-string<Model>
     */
    public function getModel(): string;
}
