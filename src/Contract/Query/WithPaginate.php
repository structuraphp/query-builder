<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Query;

use StructuraPhp\QueryBuilder\Contract\Config\PaginationInterface;

interface WithPaginate
{
    public function paginate(PaginationInterface $pagination): void;
}
