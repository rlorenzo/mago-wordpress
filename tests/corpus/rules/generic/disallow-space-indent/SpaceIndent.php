<?php

function fixture_indent( $a ) {
	$tabs = 1;
	// A tab then spaces is precision alignment, which is fine.
	$precision = array(
		'a' => 1,
		'b' => 2,
		  'continued',
	);
	// @mago-expect lint:generic/disallow-space-indent
    $spaces = 2;
	// @mago-expect lint:generic/disallow-space-indent
	if ( $a ) {
        $deeper = 3;
	}
	// @mago-expect lint:generic/disallow-space-indent
  	$mixed = 4;
	/**
	 * A doc comment aligned with a tab and a space.
		
	 */
	return $tabs;
}

// @mago-expect lint:generic/disallow-space-indent(2)
/**
    * Doc comment lines indented with spaces.
    */
function fixture_doc() {
}

// @mago-expect lint:generic/disallow-space-indent
/*
    A block comment.
 */
function fixture_block() {
	$text = <<<EOT
    heredoc text is not indentation
	EOT;
	return $text;
}

// @mago-expect lint:generic/disallow-space-indent
function fixture_closer() {
	return <<<EOT
	text
    EOT;
}
