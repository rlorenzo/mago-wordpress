<?php

declare(strict_types=1);

// lowercase_constants_pass
$ok = [true, false, null, \null];

// uppercase_and_mixed_case_constants_are_flagged
// @mago-expect lint:generic/lowercase-constant
$a = TRUE;
// @mago-expect lint:generic/lowercase-constant
$b = FalSe;
// @mago-expect lint:generic/lowercase-constant
$c = \NULL;

// default_values_are_checked
// @mago-expect lint:generic/lowercase-constant
function f($x = NULL) {}

// A class named `True` after `new` has no fixture: mago's own parser rejects it as a semantics
// error. The rule skips it by the `new` before it; checked against phpcs's own tests instead.

// type_declarations_are_left_to_lowercase_type
function g(?Foo $x = null): FALSE|null {}
