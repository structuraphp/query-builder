<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\PHPStan;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use StructuraPhp\QueryBuilder\Enum\Type;

/**
 * What ScopeConfig and ScopeReflector reject at runtime, on the calls that
 * configure a scope found on the model. Each scope gets the first error the
 * library would raise; whatever cannot be read stops the checks after it.
 *
 * @phpstan-import-type Call from QuerySnapshot
 * @phpstan-import-type Entity from QuerySnapshot
 */
final class ScopeArgumentChecker
{
    /** The options that take their arguments by name. */
    private const array OPTIONS = [
        'cast',
        'default',
        'rules',
        'each',
        'nested',
        'messages',
        'attribute',
    ];

    /** The kinds of value that tell an attribute from a nested array. */
    private const array KINDS = ['string', 'class', 'strings'];

    /** The options a context argument does not take, in runtime order. */
    private const array NOT_IN_CONTEXT = [
        'cast',
        'rules',
        'each',
        'nested',
        'messages',
        'attribute',
    ];

    /**
     * @return list<IdentifierRuleError>
     */
    public static function check(
        QuerySnapshot $query,
        ModelInspector $model,
    ): array {
        $errors = [];

        foreach ($query->scopes as $scope) {
            $error = self::scope($scope, $model);

            if ($error instanceof IdentifierRuleError) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param Entity $scope
     */
    private static function scope(
        array $scope,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $call = $scope['call'];
        $options = $scope['options'];
        $name = QuerySnapshot::string($call, 0, 'name');
        $method = array_find(
            array_reverse($options),
            static fn (array $option): bool => $option['method'] === 'method',
        );
        $alias = $method === null
            ? $name
            : QuerySnapshot::string($method, 0, 'method');
        $signature = $alias === null ? null : $model->findScope($alias);

        if ($name === null || !$signature instanceof ScopeSignature) {
            return null;
        }

        // ScopeConfig fails as soon as the option is called.
        $positional = array_find(
            $options,
            static fn (array $option): bool => \in_array(
                $option['method'],
                self::OPTIONS,
                true,
            ) && array_any(
                array_keys($option['args']),
                static fn (int|string $key): bool => \is_int($key),
            ),
        );

        if ($positional !== null) {
            return self::error($positional, \sprintf(
                'Scope "%s" options must use named arguments.',
                $name,
            ));
        }

        $context = self::context($options);

        if ($context === null) {
            return null;
        }

        $arguments = array_column($signature->parameters, 'name');

        // An attribute() of unknown kind may be a name or a nested array:
        // which error comes first is then unknown.
        $vague = self::entries($options, 'attribute', self::KINDS, false);

        if (array_diff(array_column($vague, 0), $arguments) !== []) {
            return null;
        }

        return self::unknown($options, $context, $arguments, $name)
            ?? self::arguments($scope, $name, $context, $signature, $model);
    }

    /**
     * The names given to context(), with their call; null when one cannot
     * be read.
     *
     * @param list<Call> $options
     *
     * @return null|list<array{string, Call}>
     */
    private static function context(array $options): ?array
    {
        $names = [];

        foreach ($options as $option) {
            if ($option['method'] !== 'context') {
                continue;
            }

            foreach ($option['args'] as $arg) {
                $name = \in_array($arg['kind'], ['string', 'class'], true)
                    ? $arg['value'] ?? null
                    : null;

                if (!\is_string($name)) {
                    return null;
                }

                $names[] = [$name, $option];
            }
        }

        return $names;
    }

    /**
     * The argument names an option gives, with the call that gives each
     * (twice when given twice), filtered by the kind of the value.
     *
     * @param list<Call> $options
     * @param list<string> $kinds
     *
     * @return list<array{string, Call}>
     */
    private static function entries(
        array $options,
        string $method,
        array $kinds,
        bool $in,
    ): array {
        $found = [];

        foreach ($options as $option) {
            if ($option['method'] !== $method) {
                continue;
            }

            foreach ($option['args'] as $key => $arg) {
                if (
                    \is_string($key)
                    && \in_array($arg['kind'], $kinds, true) === $in
                ) {
                    $found[] = [$key, $option];
                }
            }
        }

        return $found;
    }

    /**
     * An option that names an argument the scope does not have, in the
     * order ScopeReflector looks at them.
     *
     * @param list<Call> $options
     * @param list<array{string, Call}> $context
     * @param list<string> $arguments
     */
    private static function unknown(
        array $options,
        array $context,
        array $arguments,
        string $scope,
    ): ?IdentifierRuleError {
        $groups = [$context];

        foreach (array_diff(self::OPTIONS, ['attribute']) as $method) {
            $groups[] = self::entries($options, $method, [], false);
        }

        // An attribute() is a name for a string, else a nested array.
        $groups[] = self::entries(
            $options,
            'attribute',
            ['string', 'class'],
            true,
        );
        $groups[] = self::entries($options, 'attribute', ['strings'], true);

        foreach ($groups as $group) {
            foreach ($group as [$name, $call]) {
                if (!\in_array($name, $arguments, true)) {
                    return self::error($call, \sprintf(
                        'Scope "%s" has no argument "%s".',
                        $scope,
                        $name,
                    ));
                }
            }
        }

        return null;
    }

    /**
     * @param Entity $scope
     * @param list<array{string, Call}> $context
     */
    private static function arguments(
        array $scope,
        string $name,
        array $context,
        ScopeSignature $signature,
        ModelInspector $model,
    ): ?IdentifierRuleError {
        $options = $scope['options'];
        $contextual = array_column($context, 0);

        foreach ($signature->parameters as $parameter) {
            $argument = $parameter['name'];

            if (\in_array($argument, $contextual, true)) {
                $error = self::contextual($options, $name, $argument);

                if ($error instanceof IdentifierRuleError) {
                    return $error;
                }

                continue;
            }

            $cast = array_find(
                array_reverse($options),
                static fn (array $option): bool => $option['method'] === 'cast'
                    && \array_key_exists($argument, $option['args']),
            );
            $valid = $cast === null
                ? self::typed($parameter['type'], $model)
                : self::castable($cast['args'][$argument], $model);

            // The arguments after an unreadable one are not looked at.
            if ($valid === null) {
                return null;
            }

            if (!$valid) {
                return $cast === null
                    ? self::error($scope['call'], \sprintf(
                        'Scope "%s" argument "%s" needs a scalar, enum, date'
                        . ' or array type, or an explicit cast().',
                        $name,
                        $argument,
                    ))
                    : self::error($cast, \sprintf(
                        'Scope "%s" argument "%s" cast must be a Type, a'
                        . ' backed enum or a closure.',
                        $name,
                        $argument,
                    ));
            }
        }

        return null;
    }

    /**
     * @param list<Call> $options
     */
    private static function contextual(
        array $options,
        string $scope,
        string $argument,
    ): ?IdentifierRuleError {
        foreach (self::NOT_IN_CONTEXT as $method) {
            $call = array_find(
                $options,
                static fn (array $option): bool => $option['method'] === $method
                    && \array_key_exists($argument, $option['args']),
            );

            if ($call !== null) {
                return self::error($call, \sprintf(
                    'Scope "%s" argument "%s" comes from the context: it'
                    . ' takes no %s().',
                    $scope,
                    $argument,
                    $method,
                ));
            }
        }

        return null;
    }

    /**
     * Whether a parameter type needs no cast(): an untyped or union
     * parameter never does; null when the type is not known.
     */
    private static function typed(?string $type, ModelInspector $model): ?bool
    {
        return $type === null ? false : $model->acceptsWithoutCast($type);
    }

    /**
     * Whether a cast() value is a Type, a backed enum or a closure; null
     * when it cannot be told.
     *
     * @param array{kind: string, value?: int|list<string>|string} $arg
     */
    private static function castable(array $arg, ModelInspector $model): ?bool
    {
        $value = $arg['value'] ?? null;

        return match ($arg['kind']) {
            'closure' => true,
            // Any other enum case is a TypeError, not this check's business.
            'case' => \is_string($value)
                && strstr($value, '::', true) === Type::class
                    ? true
                    : null,
            'class' => \is_string($value)
                ? $model->isBackedEnum($value)
                : null,
            default => null,
        };
    }

    /**
     * @param Call $call
     */
    private static function error(
        array $call,
        string $message,
    ): IdentifierRuleError {
        return RuleErrorBuilder::message($message)
            ->identifier('structuraQueryBuilder.configuration')
            ->file($call['file'])
            ->line($call['line'])
            ->build();
    }
}
