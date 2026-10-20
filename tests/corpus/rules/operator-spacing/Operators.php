<?php

// The | between the types of a multi-catch is an operator.
try {
// @mago-expect lint:wordpress/operator-spacing(2)
} catch ( A|B $e ) {
}

try {
} catch ( A | B $e ) {
}

try {
} catch ( A
	| B
// @mago-expect lint:wordpress/operator-spacing
	|C $e ) {
}

// Union and intersection types, references, default values and declare are not.
function f( A|B $x, C&D $y, int &$z = null, ...$rest ): E|F {
	$a = &$b;
	$g = function ( int $n = 1 ) use ( &$a ): ?int {
		return -1;
	};
	$h = fn( $x = 1 ) => $x * 2;
	return $a;
}

class K {
	public int|string $prop = 1;
}

declare(ticks=1);

// Everything else is.
// @mago-expect lint:wordpress/operator-spacing(2)
$x = 1|2;
$x = 1 & 2;
// @mago-expect lint:wordpress/operator-spacing(4)
$y = $a?$b:$c;
$y = $a ?: $b;
// @mago-expect lint:wordpress/operator-spacing
$y = $a?? $b;
// @mago-expect lint:wordpress/operator-spacing
$y = !$a;
// @mago-expect lint:wordpress/operator-spacing(2)
$y = $a&&$b;
// @mago-expect lint:wordpress/operator-spacing
$y = $a and$b;
// @mago-expect lint:wordpress/operator-spacing
$y = $a instanceof$b;
$y = -1 + - 2;
$y .= 'a';
// @mago-expect lint:wordpress/operator-spacing(2)
$y+=1;
// @mago-expect lint:wordpress/operator-spacing(2)
$y = [ 'a'=>1, 'b' => 2 ];

// phpcs:ignore WordPress.WhiteSpace.OperatorSpacing.NoSpaceBefore
$z = 1+ 2;
