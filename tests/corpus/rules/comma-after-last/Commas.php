<?php

$fine = array(
	'a' => 1,
	'b' => 2,
);
$single = array( 1, 2 );
$empty = array();
$list = [
	1,
	2,
];

$missing = array(
	'a' => 1,
	// @mago-expect lint:wordpress/comma-after-last
	'b' => 2
);

$short = [
	1,
	// @mago-expect lint:wordpress/comma-after-last
	2
];

$closer = array(
	1,
	// @mago-expect lint:wordpress/comma-after-last
	2 );

$comment = array(
	1,
	// @mago-expect lint:wordpress/comma-after-last
	2 // two
);

$nested = array(
	'child' => array(
		'x' => 1,
		// @mago-expect lint:wordpress/comma-after-last
		'y' => 2
	),
);

// @mago-expect lint:wordpress/comma-after-last
$found = array( 1, 2, );

// @mago-expect lint:wordpress/comma-after-last
$foundShort = [ 'a' => 1, ];

[ $x, $y ] = $fine;
[ $q, $r, ] = $fine;
foreach ( $fine as [ $m, $n ] ) {
}
