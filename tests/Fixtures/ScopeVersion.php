<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Fixtures;

/**
 * String backed, with values that look like integers.
 */
enum ScopeVersion: string
{
    case One = '1';
    case Two = '2';
}
