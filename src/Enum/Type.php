<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Enum;

enum Type
{
    case String;
    case Integer;
    case Float;
    case Boolean;
    case Date;
}
