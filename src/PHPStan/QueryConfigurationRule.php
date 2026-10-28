<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks the configuration of each query class against its model, the
 * inherited configuration methods included.
 *
 * @phpstan-import-type CollectedMethod from ConfigurationCollector
 * @phpstan-import-type Entity from QuerySnapshot
 *
 * @implements Rule<CollectedDataNode>
 */
final readonly class QueryConfigurationRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflection,
    ) {}

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // A partial analysis misses the configuration of other files.
        if ($node->isOnlyFilesAnalysis()) {
            return [];
        }

        $methods = [];
        $collected = $node->get(ConfigurationCollector::class);

        foreach (array_merge(...array_values($collected)) as $method) {
            $methods[$method['class']][$method['method']] = $method;
        }

        $errors = [];
        $classes = $node->get(QueryClassCollector::class);

        foreach (array_merge(...array_values($classes)) as $class) {
            foreach ($this->check($class, $methods) as $error) {
                // Same file, line and message: an inherited method.
                $errors[serialize($error)] = $error;
            }
        }

        return array_values($errors);
    }

    /**
     * @param array<string, array<string, CollectedMethod>> $methods
     *
     * @return list<IdentifierRuleError>
     */
    private function check(string $class, array $methods): array
    {
        $snapshot = QuerySnapshotFactory::create(
            $this->reflection->getClass($class),
            $methods,
        );

        if (
            !$snapshot instanceof QuerySnapshot
            || !$this->reflection->hasClass($snapshot->model)
            || !$this->reflection->getClass($snapshot->model)
                ->isSubclassOf(Model::class)
        ) {
            return [];
        }

        $model = new ModelInspector($this->reflection, $snapshot->model);
        $name = $this->reflection->getClass($snapshot->model)->getName();
        $errors = [];

        foreach ($snapshot->scopes as $scope) {
            $error = $this->scopeNotFound($scope, $model, $name);

            if ($error instanceof IdentifierRuleError) {
                $errors[] = $error;
            }
        }

        return [...$errors, ...ConfigurationChecker::check($snapshot, $model)];
    }

    /**
     * The scope is looked up by its last method(), else by its name.
     *
     * @param Entity $scope
     */
    private function scopeNotFound(
        array $scope,
        ModelInspector $model,
        string $class,
    ): ?IdentifierRuleError {
        $call = $scope['call'];

        foreach ($scope['options'] as $option) {
            if ($option['method'] === 'method') {
                $call = $option;
            }
        }

        // scope(name: 'x'), method(method: 'y')
        $named = $call['method'] === 'method' ? 'method' : 'name';
        $name = QuerySnapshot::string($call, 0, $named);

        if (
            $name === null
            || !$model->knowsScopes()
            || $model->findScope($name) instanceof ScopeSignature
        ) {
            return null;
        }

        return RuleErrorBuilder::message(\sprintf(
            'Scope "%s" does not exist on %s.',
            $name,
            $class,
        ))
            ->identifier('structuraQueryBuilder.scopeNotFound')
            ->file($call['file'])
            ->line($call['line'])
            ->build();
    }
}
