<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\PHPStan\ConstraintClosureRule;
use StructuraPhp\QueryBuilder\PHPStan\ConstraintClosureTypeExtension;

/**
 * @extends RuleTestCase<ConstraintClosureRule>
 */
final class ConstraintClosureRuleTest extends RuleTestCase
{
    private const string DATA
        = 'Tests\StructuraPhp\QueryBuilder\PHPStan\Data\\';

    private const string RELATIONS = 'Illuminate\Database\Eloquent\Relations\\';

    public function testOnlyAWrongAnnotationIsReported(): void
    {
        $products = self::RELATIONS . 'HasMany<' . self::DATA . 'Product, '
            . self::DATA . 'Category>';

        $this->analyse([__DIR__ . '/data/constraint-rule.php'], [
            [$this->message('int', $products), 57],
            [$this->message(self::RELATIONS . 'BelongsTo', $products), 59],
            [
                $this->message(
                    self::RELATIONS . 'HasMany',
                    self::DATA . 'ProductBuilder',
                ),
                61,
            ],
        ]);
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

    protected function getRule(): Rule
    {
        return new ConstraintClosureRule(
            self::getContainer()
                ->getByType(ConstraintClosureTypeExtension::class),
        );
    }

    private function message(string $given, string $expected): string
    {
        return 'Parameter $query of the constrain() closure expects '
            . $given . ', ' . $expected . ' given.';
    }
}
