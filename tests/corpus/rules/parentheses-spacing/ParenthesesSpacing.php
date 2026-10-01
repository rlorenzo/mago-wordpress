<?php

declare(strict_types=1);

// wordpress_spacing_is_allowed
foo( $a, $b );
$o->m();
new Foo( $x );
if ( ! $x ) {
} elseif ( $y ) {
}
foreach ( $a as $k => $v ) {
}
function f( int $a = 1 ) {
}
$c = function ( $x ) use ( $y ) {
};
$arr = array( 1, 2 );
$s = [ 1 ];
$e = $arr[ $i ] . $arr['k'] . $arr[0] . $arr[-1] . $arr[+1] . $arr[ - $i ];
isset( $a );
do {
} while ( $x );

// empty_pairs_are_allowed
bar();
$empty = array();
$none = [];

// multi_line_sides_are_allowed
$multi = foo(
	$a,
	$b
);

// strings_are_left_alone
$str = "x $a[0] {$b[$i]} {$o->m($x)}";

// missing_spaces_are_flagged
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
foo($a);
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
while ($x) {
}
// One pragma above a function declaration consumes both of its reports.
// @mago-expect lint:wordpress/parentheses-spacing
function g($a) {
}
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
$short = [1];
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
$key = $arr[$i];

// extra_spaces_are_flagged
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
foo(  $a  );
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
$literal = $arr[ 'k' ];

// signed_integer_keys_take_no_spaces
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
$signed = $arr[ -1 ];

// tabs_are_not_spaces
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
if (	$x	) {
}

// parentheses_in_comments_are_skipped
// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
if /* ( */ ($x) {
}
