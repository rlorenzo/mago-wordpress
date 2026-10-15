<?php

$array = array( 1, 2 );
$var = array();
echo $var[1];

// @mago-expect lint:generic/disallow-short-array-syntax
$empty = [];
// @mago-expect lint:generic/disallow-short-array-syntax
$numbers = [ 1, 2, 3 ];
// @mago-expect lint:generic/disallow-short-array-syntax(2)
$foo = [ $var[1], [ 2 ] ];
// @mago-expect lint:generic/disallow-short-array-syntax(2)
$multi = [
	1,
	[ 2 ],
];
$array = (array) $var['key'];
function fixture_short_array( $default = array() ) {
	return $default;
}

// Short lists are not arrays.
[ $a, [ $b ] ] = $array;
list( $c, [ $d ] ) = $array;
foreach ( $array as [ $e, [ $f ] ] ) {
	echo $e, $f;
}
foreach ( $array as $k => [ $g ] ) {
	echo $k, $g;
}

// A short array in the key of a short list is an array.
// @mago-expect lint:generic/disallow-short-array-syntax
[ 'key' => $a, [ 'key' ] => $b ] = $array;
