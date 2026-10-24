<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Contract\Config;

use BackedEnum;
use Closure;
use StructuraPhp\QueryBuilder\Enum\Type;

/**
 * Every variadic method but context() takes named arguments only, keyed by
 * the scope's parameter names.
 */
interface ScopeConfigInterface
{
    public function method(string $method): static;

    /**
     * Arguments given by the code through `builder(context: ...)`, never by
     * the client.
     */
    public function context(string ...$names): static;

    /**
     * @param class-string<BackedEnum>|Closure|Type ...$casts
     */
    public function cast(Closure|string|Type ...$casts): static;

    public function default(mixed ...$defaults): static;

    /**
     * @param list<mixed> ...$rules
     */
    public function rules(array ...$rules): static;

    /**
     * @param list<mixed> ...$rules
     */
    public function each(array ...$rules): static;

    /**
     * Laravel rules by dot path inside the argument (`*.min`, `geo.lat`).
     *
     * @param array<string, list<mixed>> ...$rules
     */
    public function nested(array ...$rules): static;

    /**
     * @param array<string, string> ...$messages
     */
    public function messages(array ...$messages): static;

    /**
     * A name for the argument itself, or names by dot path inside it
     * (`['*.min' => 'minimum']`).
     *
     * @param array<string, string>|string ...$attributes
     */
    public function attribute(array|string ...$attributes): static;
}
