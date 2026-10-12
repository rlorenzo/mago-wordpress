<?php

// This one is fine.
$a = 1; // Trailing comments need a stop too!

// @mago-expect lint:generic/inline-comment

//no space.
$b = 2;

// @mago-expect lint:generic/inline-comment

//   too many spaces.
$c = 3;

// @mago-expect lint:generic/inline-comment

# Hash comment.
$d = 4;

// @mago-expect lint:generic/inline-comment

// Ends without a stop
$e = 5;

// @mago-expect lint:generic/inline-comment

// First line of a run
// Last line of a run without a stop
$f = 6;

if ( $a ) {
	$b = 1;
} // end of block comments are skipped

function fixture_function() {
	// @mago-expect lint:generic/inline-comment

	// Last comment of the function.

}
