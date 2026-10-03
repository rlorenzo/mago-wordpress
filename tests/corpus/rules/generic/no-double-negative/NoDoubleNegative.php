<?php

declare(strict_types=1);

// double_and_triple_negatives_are_flagged_once_per_chain
// @mago-expect lint:generic/no-double-negative
$a = !!$b;
// @mago-expect lint:generic/no-double-negative
$c = ! ! ! $d;
// @mago-expect lint:generic/no-double-negative
$e = !!$f instanceof Foo;

// a_single_not_and_parenthesised_nots_are_fine
$g = !$h;
$i = !(!$j);

// phpcs_ignore_silences_it
// phpcs:ignore Universal.CodeAnalysis.NoDoubleNegative.FoundDouble
$k = !!$l;
