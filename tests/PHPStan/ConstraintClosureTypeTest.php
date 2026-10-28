<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPStan\Type\ClosureType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;
use StructuraPhp\QueryBuilder\PHPStan\ConstraintClosureTypeExtension;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\ParametersQuery;
use Tests\StructuraPhp\QueryBuilder\PHPStan\Data\Tag;

final class ConstraintClosureTypeTest extends TypeInferenceTestCase
{
    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(
        string $assertType,
        string $file,
        mixed ...$args,
    ): void {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(
            __DIR__ . '/data/constraint-types.php',
        );
    }

    public function testOtherParametersAreTheClosureOnes(): void
    {
        [$node, $scope, $method] = $this->parametersCall();
        $type = self::getContainer()
            ->getByType(ConstraintClosureTypeExtension::class)
            ->getTypeFromMethodCall(
                $method,
                $node,
                $method->getOnlyVariant()->getParameters()[0],
                $scope,
            );

        self::assertInstanceOf(ClosureType::class, $type);
        self::assertSame(
            'Closure(Illuminate\Database\Eloquent\Builder<'
                . 'Tests\StructuraPhp\QueryBuilder\PHPStan\Data\Tag>, '
                . 'Tests\StructuraPhp\QueryBuilder\PHPStan\Data\User, '
                . 'mixed, int=, string ...): mixed',
            $type->describe(VerbosityLevel::precise()),
        );
        self::assertTrue(
            $type->getParameters()[2]->passedByReference()->yes(),
        );
        self::assertTrue($type->isVariadic());
    }

    public function testReadsOutsideTheFileAreTracked(): void
    {
        [$node, $real] = $this->parametersCall();
        $tracked = [];
        $track = static function (string $read) use (&$tracked): void {
            $tracked[] = $read;
        };
        $scope = self::createStubForIntersectionOfInterfaces([
            Scope::class,
            DependencyTracker::class,
        ]);
        $this->delegate($scope, $real);
        $scope->method('trackFileDependency')->willReturnCallback($track);

        self::getContainer()
            ->getByType(ConstraintClosureTypeExtension::class)
            ->expected($node, $scope);
        $files = array_map(realpath(...), $tracked);
        $counts = array_count_values(array_filter($files, is_string(...)));

        // Files, not classes: Larastan crashes when the result cache restores
        // a class dependency (undefined LARAVEL_VERSION).
        self::assertNotContains(false, $files, 'Only real files.');
        // The query, the model and the counted model.
        self::assertSame(3, $counts[$this->file(ParametersQuery::class)]);
        // An ancestor of the query, and of both models.
        self::assertSame(1, $counts[$this->file(FromModel::class)]);
        self::assertSame(2, $counts[$this->file(Model::class)]);
        // The builder of the counted model.
        self::assertSame(1, $counts[$this->file(Builder::class)]);
    }

    public function testAScopeWithoutDependencyTrackerIsTyped(): void
    {
        [$node, $real] = $this->parametersCall();
        // As PHPStan before 2.3 gives it.
        $scope = self::createStub(Scope::class);
        $this->delegate($scope, $real);

        $type = self::getContainer()
            ->getByType(ConstraintClosureTypeExtension::class)
            ->expected($node, $scope);

        self::assertSame(
            'Illuminate\Database\Eloquent\Builder<' . Tag::class . '>',
            $type?->describe(VerbosityLevel::precise()),
        );
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

    /**
     * The constrain() call of ParametersQuery, as PHPStan analyses it.
     *
     * @return array{
     *     MethodCall,
     *     DependencyTracker&Scope,
     *     ExtendedMethodReflection,
     * }
     */
    private function parametersCall(): array
    {
        $calls = [];

        self::processFile(
            __DIR__ . '/data/constraint-types.php',
            static function (Node $node, Scope $scope) use (&$calls): void {
                $method = $node instanceof MethodCall
                    ? $scope->getMethodReflection(
                        $scope->getType($node->var),
                        'constrain',
                    )
                    : null;

                if (
                    $method instanceof ExtendedMethodReflection
                    && $scope instanceof DependencyTracker
                    && $scope->getClassReflection()?->getName()
                        === ParametersQuery::class
                ) {
                    /** @var MethodCall $node */
                    $calls[] = [$node, $scope, $method];
                }
            },
        );

        self::assertCount(1, $calls);

        return $calls[0];
    }

    /**
     * @param class-string $class
     */
    private function file(string $class): string
    {
        return (string) new ReflectionClass($class)->getFileName();
    }

    /**
     * The stub answers as the scope PHPStan gave.
     */
    private function delegate(Scope&Stub $scope, Scope $real): void
    {
        $scope->method('getType')->willReturnCallback($real->getType(...));
        $scope->method('getClassReflection')
            ->willReturn($real->getClassReflection());
        $scope->method('getMethodReflection')
            ->willReturnCallback($real->getMethodReflection(...));
    }
}
