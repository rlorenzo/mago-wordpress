<?php

function language_construct_fixture( $x ) {
	echo 'fine';
	return( 1 );
	// @mago-expect lint:wordpress/language-construct-spacing
	echo
		'next line';
	// @mago-expect lint:wordpress/language-construct-spacing
	return   $x;
}
