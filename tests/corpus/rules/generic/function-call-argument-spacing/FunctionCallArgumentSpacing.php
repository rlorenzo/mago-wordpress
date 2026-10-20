<?php

// Valid: one space after each comma, none before.
foo( $a, $b, $c );
foo( $a, $b, array( 1, 2 ), $d );
foo(
	$a,
	$b
);
foo( function ( $x, $y ) { return array( $x , $y ); }, $b );
// @mago-expect lint:generic/function-call-argument-spacing
foo( fn( $x ) => bar( $x , 1 ), $b );
foo( [ 1, 2 , 3 ], $b );
$o->method( $a, $b );
new Foo( $a, $b );

// Wrong spacing.
// @mago-expect lint:generic/function-call-argument-spacing
foo( $a,$b );
// @mago-expect lint:generic/function-call-argument-spacing(2)
foo( $a ,$b );
// @mago-expect lint:generic/function-call-argument-spacing
foo( $a , $b );
// @mago-expect lint:generic/function-call-argument-spacing
foo( $a,   $b );
// @mago-expect lint:generic/function-call-argument-spacing(2)
foo( $a, $b ,$c );
// @mago-expect lint:generic/function-call-argument-spacing
isset( $a,$b );
// @mago-expect lint:generic/function-call-argument-spacing
$fn( $a,$b );
// @mago-expect lint:generic/function-call-argument-spacing
$o->method( $a,$b );
// @mago-expect lint:generic/function-call-argument-spacing
self::method( $a,$b );
// @mago-expect lint:generic/function-call-argument-spacing
foo( $a, bar( $b,$c ) );
foo( 1, 2,
	3 );

// A comma that starts a line is moved to the end of the previous one.
foo( $a
// @mago-expect lint:generic/function-call-argument-spacing
	, $b );

// A trailing comma is not this sniff's business.
foo( $a, $b,);

// A declaration is not a call.
function bar( $a ,$b ) {}
