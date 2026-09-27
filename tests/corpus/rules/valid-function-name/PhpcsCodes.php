<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
function myPluginSetup(): void {}

// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore
function __myplugin_setup(): void {}

// @mago-expect lint:wordpress/valid-function-name
// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
function myPluginOther(): void {}

class Myplugin_Widget
{
    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
    public function renderWidget(): void {}

    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodDoubleUnderscore
    public function __render(): void {}

    // @mago-expect lint:wordpress/valid-function-name
    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    public function renderOther(): void {}
}
