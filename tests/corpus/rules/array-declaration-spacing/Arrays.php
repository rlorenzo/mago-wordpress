<?php

$fine = array(
	'a' => 1,
	'b' => 2,
);
$single = array( 'only' => 1 );
$list = array( 1, 2, 3 );
[ 'x' => $x, 'y' => $y ] = $fine;

// @mago-expect lint:wordpress/array-declaration-spacing
$keyed = array( 'a' => 1, 'b' => 2 );

// @mago-expect lint:wordpress/array-declaration-spacing
$shared = array(
	'a' => 1, 'b' => 2,
	'c' => 3,
);
