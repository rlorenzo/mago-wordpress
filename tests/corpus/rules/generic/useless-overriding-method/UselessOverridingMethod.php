<?php

declare(strict_types=1);

class UselessOverridingParent
{
    public function a(int $x, int $y): int
    {
        return $x + $y;
    }

    public function b(int ...$rest): int
    {
        return count($rest);
    }
}

final class UselessOverridingChild extends UselessOverridingParent
{
    // passing_the_parameters_through_is_flagged
    // @mago-expect lint:generic/useless-overriding-method
    public function a(int $x, int $y): int
    {
        return parent::a($x, $y);
    }

    // a_variadic_spread_does_not_match_the_signature_text
    public function b(int ...$rest): int
    {
        return parent::b(...$rest);
    }

    // phpcs_ignore_silences_it
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
    public function c(): void
    {
        parent::c();
    }
}

final class UselessOverridingChanged extends UselessOverridingParent
{
    // changed_arguments_or_extra_code_are_fine
    public function a(int $x, int $y): int
    {
        return parent::a($y, $x);
    }

    public function b(int ...$rest): int
    {
        $count = parent::b(...$rest);

        return $count;
    }
}
