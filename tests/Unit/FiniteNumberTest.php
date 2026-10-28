<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use PHPUnit\Framework\TestCase;
use StructuraPhp\QueryBuilder\Rule\FiniteNumber;

final class FiniteNumberTest extends TestCase
{
    /**
     * Scramble reads a rule object through its string form.
     */
    public function testDocumentationReadsTheBoundsOfAFloat(): void
    {
        $rule = (string) new FiniteNumber();

        self::assertSame(
            'between:-1.7976931348623157e308,1.7976931348623157e308',
            $rule,
        );
    }
}
