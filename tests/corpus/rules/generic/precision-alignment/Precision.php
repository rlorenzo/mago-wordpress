<?php

function fixture_precision( $a ) {
	$aligned = 1;
	$stops = array(
		'a' => 1,
		// @mago-expect lint:generic/precision-alignment
		  'b' => 2,
	);
    $four = 4;
	// @mago-expect lint:generic/precision-alignment
	  $call = array_merge(
		$stops,
		$stops
	);
	/**
	 * A doc comment with the usual one space before each star.
		
	 */
	return $four;
}

// @mago-expect lint:generic/precision-alignment
function fixture_loose() {
      $six = 6;
}

// @mago-expect lint:generic/precision-alignment
/**
	  * A doc line whose star is two spaces past a tab stop.
 */
function fixture_doc() {
	// @mago-expect lint:generic/precision-alignment
	$text = <<<EOT
	text
  EOT;
}
