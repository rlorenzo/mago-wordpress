<?php

function fixture_scope( $a ) {
	if ( $a ) {
		$ok = 1;
	$short = 2; // @mago-expect lint:generic/scope-indent
	}
	foreach ( array( 1, 2 ) as $n ) {
		echo $n;
	  } // @mago-expect lint:generic/scope-indent
	return $a;
}
