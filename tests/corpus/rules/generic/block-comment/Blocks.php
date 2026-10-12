<?php
/*
 * Fine: starts on its own line.
 */
$a = 1;

/* A one-line block comment is left alone. */
$b = 2;

// @mago-expect lint:generic/block-comment

/* */
$empty = 2;

// @mago-expect lint:generic/block-comment

/* Text on the opening line
 * continues here.
 */
$c = 3;

// @mago-expect lint:generic/block-comment

/*
 */
$d = 4;

// @mago-expect lint:generic/block-comment

/*
   lower case start without stars.
 */
$e = 5;

// @mago-expect lint:generic/block-comment
$f = 6;
/*
 * No blank line before it.
 */
$g = 7;

if (
	$a
	&& $b
):
	/*
	 * Right after a multi-line alternative-syntax opener.
	 */
	$h = 8;
endif;

/* One */ /* Two */
$i = 9;
