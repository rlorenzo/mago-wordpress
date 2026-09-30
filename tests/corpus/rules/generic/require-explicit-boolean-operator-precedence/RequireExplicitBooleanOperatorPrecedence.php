<?php

declare(strict_types=1);

// mixed_and_or_is_flagged
// @mago-expect lint:generic/require-explicit-boolean-operator-precedence
$result = $a && $b || $c;

// each_change_of_operator_is_flagged
// @mago-expect lint:generic/require-explicit-boolean-operator-precedence(2)
$result = $a && $b || $c && $d;

// word_and_symbol_operators_differ
// @mago-expect lint:generic/require-explicit-boolean-operator-precedence
$result = ($a && $b and $c);

// parenthesized_groups_are_fine
$result = ($a && $b) || $c;
$result = $a && ($b || $c) && $d;

// same_operator_chain_is_fine
$result = $a || $b || $c;

// call_arguments_are_separate_expressions
$result = $a && check($b || $c);

// ternary_branches_are_separate
$result = $a || $b ? $c && $d : $e;
