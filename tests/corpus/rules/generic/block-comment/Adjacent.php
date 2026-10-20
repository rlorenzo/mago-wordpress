<?php

// A one-line comment followed by a comment on the next line is a run of two lines.
$a = array(
	// @mago-expect lint:generic/block-comment
	'b' => true, /* internal use only. */
	/* translators: %s: Number. */
	'c' => 1,
);
