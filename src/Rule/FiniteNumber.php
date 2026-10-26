<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;
use Stringable;

/**
 * `numeric` accepts `1e999`, which PHP casts to infinity. A rule object of
 * its own, so its message never collides with a `between` of the
 * application on the same field.
 */
final readonly class FiniteNumber implements Stringable, ValidationRule
{
    /**
     * Documentation generators (Scramble) read a rule object through its
     * string form: they get the bounds of a float.
     */
    #[Override]
    public function __toString(): string
    {
        return 'between:-1.7976931348623157e308,1.7976931348623157e308';
    }

    #[Override]
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (is_numeric($value) && !is_finite((float) $value)) {
            $fail('validation.numeric')->translate();
        }
    }
}
