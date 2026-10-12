<?php

try {
	do_something();
} catch ( Exception $e ) {
	// Ignored on purpose.
}

try {
	do_something();
} catch ( Exception $e ) {
	handle( $e );
}

try {
	do_something();
// @mago-expect lint:generic/empty-catch-comment
} catch ( Exception $e ) {
}
