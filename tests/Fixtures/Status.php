<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
