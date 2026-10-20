<?php

declare(strict_types=1);

$a = 1;
$b = 2;

for ( $i = 0; $i < 3; $i++ ) {
	echo $i;
}

for ( ; ; ) {
	break;
}

// @mago-expect lint:generic/disallow-multiple-statements
$c = 3; $d = 4;

// @mago-expect lint:generic/disallow-multiple-statements
function f() { $x = 1; return $x; }

$g = 6; $h = 7; // phpcs:ignore Generic.Formatting.DisallowMultipleStatements.SameLine

// A `;` or `)` ending an interpolated string is part of the string, not a statement end.
$a = "SELECT {$b};";
$c = "({$d})";
