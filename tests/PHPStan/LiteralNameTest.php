<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\Methods\CallMethodsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\Contract\Config\FilterInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\SortInterface;

/**
 * @extends RuleTestCase<CallMethodsRule>
 */
final class LiteralNameTest extends RuleTestCase
{
    public function testNamesMustBeLiteral(): void
    {
        $this->analyse([__DIR__ . '/data/literal-names.php'], [
            [
                \sprintf(
                    'Parameter #1 $name of method %s::filter() expects '
                    . 'literal-string, string given.',
                    FilterInterface::class,
                ),
                18,
            ],
            [
                \sprintf(
                    'Parameter #1 $name of method %s::sort() expects '
                    . 'literal-string, string given.',
                    SortInterface::class,
                ),
                26,
            ],
            [
                \sprintf(
                    'Parameter #1 $fields of method %s::fields() expects '
                    . 'list<literal-string>, array{string} given.',
                    RelationConfigInterface::class,
                ),
                33,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        // A class constant would trip phpstanApi.classConstant.
        $rule = implode('\\', [
            'PHPStan',
            'Rules',
            'Methods',
            'CallMethodsRule',
        ]);

        return self::getContainer()->getByType($rule);
    }
}
