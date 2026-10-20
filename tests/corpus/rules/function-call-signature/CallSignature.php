<?php

// Valid.
foo( $a, $b );
foo();
foo( array( 1, 2 ) );
$o->method( 'a' );
new Foo( $a );
foo(
	'a',
	array(
		'b' => 1,
	),
	bar( 'c' )
);
isset( $a );
exit( 1 );
$callable( $a );

// Spaces before the parenthesis and after the closing one.
// @mago-expect lint:wordpress/function-call-signature
foo ( $a );
// @mago-expect lint:wordpress/function-call-signature
$o->method  ( $a );
// @mago-expect lint:wordpress/function-call-signature
foo( $a ) ;

// Spaces inside a single-line call.
// @mago-expect lint:wordpress/function-call-signature(2)
foo($a);
// @mago-expect lint:wordpress/function-call-signature
foo( $a);
// @mago-expect lint:wordpress/function-call-signature
foo($a );
// @mago-expect lint:wordpress/function-call-signature(2)
foo(  $a  );
// @mago-expect lint:wordpress/function-call-signature
foo(	$a );
// @mago-expect lint:wordpress/function-call-signature(2)
foo(  );
// @mago-expect lint:wordpress/function-call-signature(2)
isset($a);
// @mago-expect lint:wordpress/function-call-signature(2)
$callable($a);
// @mago-expect lint:wordpress/function-call-signature(2)
new Foo($a);

// Spaces inside a call that has only a comment.
foo( /* none */ );

// A multi-line call.
// @mago-expect lint:wordpress/function-call-signature
foo( 'a',
	'b'
);
// @mago-expect lint:wordpress/function-call-signature(2)
foo( 'a', 'b',
	'c'
);
// @mago-expect lint:wordpress/function-call-signature(2)
foo(
	'a',
	'b' );
// @mago-expect lint:wordpress/function-call-signature
foo(

	'a',
	'b'
);
// @mago-expect lint:wordpress/function-call-signature
foo(
	'a',

	'b'
);
// @mago-expect lint:wordpress/function-call-signature(3)
foo(
'a',
		'b',
	'c'
	);
foo(
	'a',
	array(
		'b',
	'c',
	),
	'd'
);
// @mago-expect lint:wordpress/function-call-signature
foo(
	'a'
	);

// Indentation of the call.
// @mago-expect lint:wordpress/function-call-signature(2)
	  foo(
		'a'
	  );
