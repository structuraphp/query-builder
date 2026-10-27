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
                    25, 77, 80, 83, 86, 90, 91, 92,
                    98, 99, 100, 101, 102, 104, 105, 107,
                    108, 121, 141, 150,
                ],
            ),
        );
    }

    protected function getRule(): Rule
    {
        return new FlatConfigurationRule();
    }
}
