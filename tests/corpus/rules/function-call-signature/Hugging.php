<?php

function laid_out() {
	foo(
		'a',
		function () {
			return 1;
		}
	);
	foo( 'a', 'b' );
}

function hugging() {
	// @mago-expect lint:wordpress/function-call-signature
	// @mago-expect lint:wordpress/function-call-signature
	add_action( 'init', function () {
		return 1;
		// @mago-expect lint:wordpress/function-call-signature
	} );

	// @mago-expect lint:wordpress/function-call-signature
	foo( 'a',
		'b'
	);
}
