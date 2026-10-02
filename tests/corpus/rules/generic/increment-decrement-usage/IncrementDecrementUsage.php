<?php

declare(strict_types=1);

// adding_or_subtracting_one_is_flagged
// @mago-expect lint:generic/increment-decrement-usage
$i = $i + 1;
// @mago-expect lint:generic/increment-decrement-usage
$i += 1;
// @mago-expect lint:generic/increment-decrement-usage
$i -= 1;

// increments_in_arithmetic_or_concatenation_are_flagged
// @mago-expect lint:generic/increment-decrement-usage
$total = $i++ + 5;
// @mago-expect lint:generic/increment-decrement-usage
$label = 'Item ' . $i++;

// other_amounts_and_variables_are_fine
$i += 2;
$i = $j + 1;
$i += $step;
++$i;
$label = 'Item ' . ($i++);

// phpcs_ignore_silences_it
// phpcs:ignore Squiz.Operators.IncrementDecrementUsage.Found
$i = $i - 1;
