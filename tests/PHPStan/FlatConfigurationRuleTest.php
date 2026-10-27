<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\PHPStan\FlatConfigurationRule;

/**
 * @extends RuleTestCase<FlatConfigurationRule>
 */
final class FlatConfigurationRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'Query configuration must be a flat list '
        . 'of calls: no condition, helper or parent call.';

    public function testConfigurationMustBeFlat(): void
    {
        $this->analyse(
            [__DIR__ . '/data/flat-configuration.php'],
            array_map(
                static fn (int $line): array => [self::MESSAGE, $line],
                [
                    27, 79, 82, 85, 88, 92, 93, 94,
                    100, 101, 102, 103, 104, 106, 107, 109,
                    110, 123, 143, 152,
                ],
            ),
        );
    }

    protected function getRule(): Rule
    {
        return new FlatConfigurationRule();
    }
}
