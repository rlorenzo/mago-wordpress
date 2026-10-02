<?php

declare(strict_types=1);

final class StaticInFinalClassCases
{
    public const NAME = 'a';

    // return_type_and_uses_in_the_body_are_flagged
    // @mago-expect lint:generic/static-in-final-class
    public function make(): static
    {
        // @mago-expect lint:generic/static-in-final-class
        $name = static::NAME;
        // @mago-expect lint:generic/static-in-final-class
        $same = $this instanceof static;
        // @mago-expect lint:generic/static-in-final-class
        $copy = new static();

        // closures_are_left_alone
        $f = static function (): string {
            return static::class;
        };

        // phpcs_ignore_silences_it
        // phpcs:ignore Universal.CodeAnalysis.StaticInFinalClass.ScopeResolution
        $other = static::NAME;

        return $same && $f() !== $name . $other ? $copy : $this;
    }
}

class StaticInOpenClass
{
    // a_class_that_is_not_final_is_left_alone
    public function make(): static
    {
        return new static();
    }
}
