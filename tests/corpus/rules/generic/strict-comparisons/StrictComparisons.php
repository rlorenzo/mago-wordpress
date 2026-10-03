<?php

declare(strict_types=1);

// loose_equal_is_flagged
// @mago-expect lint:generic/strict-comparisons
if (true == $value) {
}

// loose_not_equal_and_angle_brackets_are_flagged
// @mago-expect lint:generic/strict-comparisons(2)
$x = $a != $b || $a <> $c;

// strict_comparisons_are_fine
$y = $a === $b && $a !== $c && $a < $b;

// phpcs_ignore_silences_it
// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
$z = $a == $b;

// a_sibling_code_does_not_silence_it
// @mago-expect lint:generic/strict-comparisons
// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
$w = $a != $b;
