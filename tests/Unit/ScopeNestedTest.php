<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Range;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class ScopeNestedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('articles')->insert([
            ['title' => 'Foo', 'views' => 5],
            ['title' => 'Bar', 'views' => 50],
            ['title' => 'Baz', 'views' => 500],
        ]);
    }

    public function testNestedRulesAcceptAValidList(): void
    {
        $titles = $this->titles(['inRanges' => ['ranges' => [
            ['min' => '1', 'max' => '10'],
            ['min' => '100', 'max' => '1000'],
        ]]]);

        self::assertSame(['Foo', 'Baz'], $titles);
    }

    public function testNestedRulesRejectAMissingField(): void
    {
        $errors = $this->errors(['inRanges' => ['ranges' => [
            ['min' => '1'],
        ]]]);

        self::assertArrayHasKey('inRanges.ranges.0.max', $errors);
    }

    public function testNestedRuleComparesFields(): void
    {
        $errors = $this->errors(['inRanges' => ['ranges' => [
            ['min' => '30', 'max' => '20'],
        ]]]);

        self::assertArrayHasKey('inRanges.ranges.0.max', $errors);
    }

    public function testNestedRulesDescribeAnObject(): void
    {
        $titles = $this->titles(['viewsWithin' => ['range' => [
            'min' => '10',
            'max' => '100',
        ]]]);
        $errors = $this->errors(['viewsWithin' => ['range' => [
            'min' => 'foo',
            'max' => '100',
        ]]]);

        self::assertSame(['Bar'], $titles);
        self::assertArrayHasKey('viewsWithin.range.min', $errors);
    }

    public function testNestedRequiredFiresOnlyWhenTheArgumentIsGiven(): void
    {
        $absent = $this->errors([]);
        $missing = $this->errors(['viewsWithin' => ['range' => [
            'max' => '100',
        ]]]);
        $defaulted = $this->errors(
            ['ofStatusAmong' => ['status' => 'draft']],
            static function (ScopeInterface $scope): void {
                $scope->scope('ofStatusAmong')
                    ->default(ids: [1])
                    ->nested(ids: ['first' => ['required']]);
            },
        );

        self::assertSame([], $absent);
        self::assertArrayHasKey('viewsWithin.range.min', $missing);
        self::assertSame([], $defaulted);
    }

    public function testNestedMessages(): void
    {
        $errors = $this->errors(['inRanges' => ['ranges' => [
            ['max' => '10'],
        ]]]);

        self::assertSame(
            ['Min obligatoire.'],
            $errors['inRanges.ranges.0.min'] ?? null,
        );
    }

    public function testNestedAttributes(): void
    {
        $scope = static function (ScopeInterface $scope): void {
            $scope->scope('inRanges')
                ->nested(ranges: ['*.min' => ['required']])
                ->attribute(ranges: 'plages')
                ->attribute(ranges: ['*.min' => 'minimum']);
        };

        $missing = $this->errors(
            ['inRanges' => ['ranges' => [['max' => '1']]]],
            $scope,
        );
        $scalar = $this->errors(['inRanges' => ['ranges' => 'foo']], $scope);

        self::assertSame(
            ['The minimum field is required.'],
            $missing['inRanges.ranges.0.min'] ?? null,
        );
        self::assertSame(
            ['The plages field must be an array.'],
            $scalar['inRanges.ranges'] ?? null,
        );
    }

    public function testArrayArgumentRejectsNestedArraysByDefault(): void
    {
        $scope = static function (ScopeInterface $scope): void {
            $scope->scope('ofStatuses');
        };

        $errors = $this->errors(
            ['ofStatuses' => ['statuses' => [['draft']]]],
            $scope,
        );

        self::assertArrayHasKey('ofStatuses.statuses.0', $errors);
    }

    public function testEachAllowsNestedArrays(): void
    {
        $scope = static function (ScopeInterface $scope): void {
            $scope->scope('inRanges')->each(ranges: ['array']);
        };

        $titles = $this->titles(
            ['inRanges' => ['ranges' => [['min' => '1', 'max' => '10']]]],
            $scope,
        );

        self::assertSame(['Foo'], $titles);
    }

    public function testCastBuildsAnObjectFromValidData(): void
    {
        $titles = $this->titles(['viewsInRange' => ['range' => [
            'min' => '10',
            'max' => '100',
        ]]]);

        self::assertSame(['Bar'], $titles);
    }

    public function testCastIsSkippedWhenTheStructureIsInvalid(): void
    {
        $errors = $this->errors(['viewsInRange' => ['range' => [
            'max' => '100',
        ]]]);

        self::assertSame(['viewsInRange.range.min'], array_keys($errors));
    }

    public function testCastFailureOnValidDataIsAValidationError(): void
    {
        $scope = static function (ScopeInterface $scope): void {
            $scope->scope('viewsInRange')->cast(
                range: static fn (
                    mixed $v,
                ): Range => throw new RuntimeException(),
            );
        };

        $errors = $this->errors(
            ['viewsInRange' => ['range' => ['min' => '1', 'max' => '2']]],
            $scope,
        );

        self::assertSame(['viewsInRange.range'], array_keys($errors));
    }

    /**
     * @param array<string, mixed> $input
     * @param null|(Closure(ScopeInterface): void) $scope
     *
     * @return list<mixed>
     */
    private function titles(array $input, ?Closure $scope = null): array
    {
        $parameters = QueryParameters::fromArray(
            new ConfigurableQuery(scope: $scope ?? $this->scopes(...)),
            $input,
        );
        $query = $parameters->builder();
        $query->getQuery()->orderBy('id');

        return array_values($query->pluck('title')->all());
    }

    /**
     * @param array<string, mixed> $input
     * @param null|(Closure(ScopeInterface): void) $scope
     *
     * @return array<mixed>
     */
    private function errors(array $input, ?Closure $scope = null): array
    {
        try {
            QueryParameters::fromArray(
                new ConfigurableQuery(scope: $scope ?? $this->scopes(...)),
                $input,
            );
        } catch (ValidationException $validationException) {
            return $validationException->errors();
        }

        return [];
    }

    private function scopes(ScopeInterface $scope): void
    {
        $scope->scope('inRanges')
            ->nested(ranges: [
                '*' => ['array:min,max'],
                '*.min' => ['required', 'integer'],
                '*.max' => ['required', 'integer', 'gte:inRanges.ranges.*.min'],
            ])
            ->messages(ranges: ['*.min.required' => 'Min obligatoire.']);
        $scope->scope('viewsWithin')->nested(range: [
            'min' => ['required', 'integer'],
            'max' => ['required', 'integer'],
        ]);

        $scope->scope('viewsInRange')
            ->nested(range: [
                'min' => ['required', 'integer'],
                'max' => ['required', 'integer'],
            ])
            ->cast(range: $this->range(...));
    }

    /**
     * @param array{min: numeric-string, max: numeric-string} $range
     */
    private function range(array $range): Range
    {
        return new Range(min: (int) $range['min'], max: (int) $range['max']);
    }
}
