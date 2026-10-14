<?php

/**
 * A doc comment without inline tabs.
 */
function fixture_tabs( $a ) {
	$indented = 1; // indentation tabs are fine
	// @mago-expect lint:generic/disallow-inline-tabs
	$aligned	= 2;
	// @mago-expect lint:generic/disallow-inline-tabs
	$text = 3; //	comment	with tabs
	// @mago-expect lint:generic/disallow-inline-tabs
	/* block	comment */
	$string = "a	b"; // a tab inside a string is fine
}

// @mago-expect lint:generic/disallow-inline-tabs
$x	= 1;

?>
<div>	tabs	in	inline	HTML	are	fine</div>
<?php

$annotated = 1; // phpcs:ignore Some.Sniff.Code	-- a tab here is not reported, as phpcs reads this comment as an annotation
