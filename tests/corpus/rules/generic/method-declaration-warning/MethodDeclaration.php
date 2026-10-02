<?php

declare(strict_types=1);

final class MethodDeclarationWarningCases
{
    // single_leading_underscore_is_flagged
    // @mago-expect lint:generic/method-declaration-warning
    private function _a(): void {}

    // magic_double_underscore_and_a_lone_underscore_are_fine
    public function __toString(): string
    {
        return '';
    }

    public function _(): void {}

    // phpcs_ignore_silences_it
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    private function _b(): void {}

    // a_sibling_code_does_not
    // @mago-expect lint:generic/method-declaration-warning
    // phpcs:ignore PSR2.Methods.MethodDeclaration.StaticBeforeVisibility
    private function _c(): void {}
}
