<?php

declare(strict_types=1);

final class MethodScopeCases
{
    // method_without_visibility_is_flagged
    // @mago-expect lint:generic/method-scope
    function a(): void {}

    // static_alone_is_not_a_visibility
    // @mago-expect lint:generic/method-scope
    static function b(): void {}

    public function c(): void
    {
        // closures_and_nested_functions_are_not_methods
        $f = function (): void {};
        $f();
    }

    // phpcs_ignore_silences_it
    // phpcs:ignore Squiz.Scope.MethodScope.Missing
    function d(): void {}
}

interface MethodScopeInterface
{
    // interface_methods_are_checked_too
    // @mago-expect lint:generic/method-scope
    function e(): void;
}

function method_scope_plain_function(): void {}
