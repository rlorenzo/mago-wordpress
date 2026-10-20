<?php

function fixture_empty_lines( $a ) {
	$one = 1;

	// A single empty line is fine.
	// @mago-expect lint:generic/superfluous-whitespace
	$two = 2;


	return $one + $two;
}

$outside = 1;



$outside_again = 2;
