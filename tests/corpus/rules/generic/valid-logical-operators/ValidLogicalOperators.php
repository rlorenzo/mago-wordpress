<?php

declare(strict_types=1);

// and_and_or_are_flagged_in_any_case
// @mago-expect lint:generic/valid-logical-operators(2)
if ($a and $b OR $c) {
}

// symbols_and_xor_are_fine
$x = $a && $b || $c;
$y = $a xor $b;

// phpcs_ignore_silences_it
// phpcs:ignore Squiz.Operators.ValidLogicalOperators.NotAllowed
$z = $a or $b;
