<?php

declare(strict_types=1);

abstract class MethodDeclarationCases
{
    // fine
    final public static function a(): void {}

    // static_before_visibility
    // @mago-expect lint:generic/method-declaration
    static public function b(): void {}

    // final_after_visibility
    // @mago-expect lint:generic/method-declaration
    public final function c(): void {}

    // abstract_after_visibility
    // @mago-expect lint:generic/method-declaration
    protected abstract function d(): void;

    // no_visibility_is_method_scope_not_this_rule
    static function e(): void {}

    // underscore_is_the_warning_rule
    public function _f(): void {}

    // phpcs_ignore_silences_it
    // phpcs:ignore PSR2.Methods.MethodDeclaration.StaticBeforeVisibility
    static public function g(): void {}
}
