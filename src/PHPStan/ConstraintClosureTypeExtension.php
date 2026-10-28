<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MethodParameterClosureTypeExtension as TypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use StructuraPhp\QueryBuilder\Contract\Config\CountConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Config\RelationConfigInterface;
use StructuraPhp\QueryBuilder\Contract\Query\FromModel;

/**
 * Gives the first parameter of a constrain() closure the relation, or the
 * builder of the counted model, from declared types only: no type at all
 * when the exact one cannot be known.
 */
final readonly class ConstraintClosureTypeExtension implements TypeExtension
{
    private const array CONFIGS = [
        RelationConfigInterface::class,
        CountConfigInterface::class,
    ];

    public function __construct(
        private ReflectionProvider $reflection,
        private Parser $parser,
    ) {}

    public function isMethodSupported(
        MethodReflection $methodReflection,
        ParameterReflection $parameter,
    ): bool {
        return $this->supports($methodReflection);
    }

    public function supports(MethodReflection $method): bool
    {
        return $method->getName() === 'constrain'
            && \in_array(
                $method->getDeclaringClass()->getName(),
                self::CONFIGS,
                true,
            );
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): ?Type {
        $type = $this->expected($methodCall, $scope);

        if (!$type instanceof Type) {
            return null;
        }

        // Only called for a closure argument: its parameters as declared.
        $acceptor = $scope->getType($methodCall->getArgs()[0]->value)
            ->getCallableParametersAcceptors($scope)[0];

        return new ClosureType(
            [
                new QueryParameter($type),
                ...\array_slice($acceptor->getParameters(), 1),
            ],
            new MixedType(),
            $acceptor->isVariadic(),
        );
    }

    /**
     * The type the first parameter of the closure receives, null when the
     * exact one cannot be known.
     */
    public function expected(MethodCall $methodCall, Scope $scope): ?Type
    {
        $creator = $methodCall->var;

        // Back to the call made on the parameter, through the options.
        while (
            $creator instanceof MethodCall
            && !$creator->var instanceof Variable
        ) {
            $creator = $creator->var;
        }

        if (
            !$creator instanceof MethodCall
            || !$creator->name instanceof Identifier
            || $creator->getArgs() === []
        ) {
            return null;
        }

        $names = $scope->getType($creator->getArgs()[0]->value)
            ->getConstantStrings();
        $model = $this->model($scope->getClassReflection(), $scope);

        if (\count($names) !== 1 || $model === null) {
            return null;
        }

        $name = $names[0]->getValue();

        return match ($creator->name->toLowerString()) {
            'relation' => $this->relation($model, $name, $scope),
            'count' => $this->count($model, $name, $scope),
            default => null,
        };
    }

    /**
     * The model a final query class returns as `X::class` from getModel():
     * a subclass may return another one.
     */
    private function model(?ClassReflection $query, Scope $scope): ?string
    {
        if (
            $query?->isFinal() !== true
            || !$query->implementsInterface(FromModel::class)
        ) {
            return null;
        }

        $declaring = $query->getNativeMethod('getModel')
            ->getDeclaringClass();

        // Read without PHPStan: a change of its body is not seen otherwise.
        $this->track($scope, $declaring->getName());

        /** @var null|Class_ $class */
        $class = new NodeFinder()->findFirst(
            $this->parser->parseFile($declaring->getFileName() ?? ''),
            static fn (Node $node): bool => $node instanceof Class_
                && (string) $node->namespacedName === $declaring->getName(),
        );
        $return = $class?->getMethod('getModel')->stmts[0] ?? null;
        $expr = $return instanceof Return_ ? $return->expr : null;

        if (
            !$expr instanceof ClassConstFetch
            || !$expr->class instanceof Name
            || !$expr->name instanceof Identifier
            || $expr->name->toLowerString() !== 'class'
        ) {
            return null;
        }

        $model = $expr->class->toString();

        return new ObjectType(Model::class)
            ->isSuperTypeOf(new ObjectType($model))
            ->yes() ? $model : null;
    }

    /**
     * The declared type of the last relation of the path.
     */
    private function relation(
        string $model,
        string $path,
        Scope $scope,
    ): ?Type {
        $type = null;

        foreach (explode('.', $path) as $name) {
            $related = $this->related($model, $name, $scope);

            if ($related === null) {
                return null;
            }

            [$type, $model] = $related;
        }

        return $type;
    }

    /**
     * The builder of the counted model, which withCount() passes.
     */
    private function count(string $model, string $name, Scope $scope): ?Type
    {
        $related = $this->related($model, $name, $scope)[1] ?? null;

        if ($related === null) {
            return null;
        }

        // Its builder comes from its declaration, which the file may not use.
        $this->track($scope, $related);
        $builder = new ModelInspector($this->reflection, $related)
            ->builderClass();

        if ($builder === null) {
            return null;
        }

        $this->track($scope, $builder);

        return $builder === Builder::class
            ? new GenericObjectType(Builder::class, [new ObjectType($related)])
            : new ObjectType($builder);
    }

    /**
     * The declared return type of a relation method, and its related model.
     *
     * @return null|array{Type, class-string<Model>}
     */
    private function related(
        string $model,
        string $name,
        Scope $scope,
    ): ?array {
        // Only reached through reflection, not named by the analysed file.
        $this->track($scope, $model);
        $method = $scope->getMethodReflection(new ObjectType($model), $name);

        if (!$method instanceof ExtendedMethodReflection) {
            return null;
        }

        $type = ParametersAcceptorSelector::combineAcceptors(
            $method->getVariants(),
        )->getReturnType();
        $classes = $type->getTemplateType(Relation::class, 'TRelatedModel')
            ->getObjectClassReflections();

        // Only a relation has a related model.
        if (
            \count($classes) !== 1
            || $classes[0]->getName() === Model::class
        ) {
            return null;
        }

        /** @var class-string<Model> $related */
        $related = $classes[0]->getName();

        return [$type, $related];
    }

    /**
     * Tells the result cache, from PHPStan 2.3 which has DependencyTracker,
     * the files of the class and its ancestors. Not the class: restoring a
     * class dependency runs the stub files extensions before the bootstrap
     * files, and Larastan's crashes (undefined LARAVEL_VERSION).
     */
    private function track(Scope $scope, string $class): void
    {
        if (!$scope instanceof DependencyTracker) {
            return;
        }

        $ancestors = $this->reflection->getClass($class)->getAncestors();

        foreach ($ancestors as $declaring) {
            // A built-in one has no file, or only a PHPStan stub.
            $file = $declaring->isBuiltin() ? null : $declaring->getFileName();

            if ($file !== null) {
                $scope->trackFileDependency($file);
            }
        }
    }
}
