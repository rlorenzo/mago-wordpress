<?php

function fixture_trailing( $a ) {
	// @mago-expect lint:generic/superfluous-whitespace
	$trailing = 1;  
	// @mago-expect lint:generic/superfluous-whitespace
	// A comment with trailing space 
	// @mago-expect lint:generic/superfluous-whitespace
	
	$ok = <<<EOT
trailing space in a heredoc is fine  
EOT;
	return $trailing . $ok;
}
