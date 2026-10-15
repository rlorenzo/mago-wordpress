<?php

$long = $compare ? 'a' : 'b';

// @mago-expect lint:generic/disallow-short-ternary
$short = isset( $a ) ? : 0;
// @mago-expect lint:generic/disallow-short-ternary(2)
$chained = ! empty( $b ) ?: ! empty( $c ) ?: 0;
// @mago-expect lint:generic/disallow-short-ternary
$comment = $compare ? /* intentionally left empty */
	: 0;
// @mago-expect lint:generic/disallow-short-ternary
$question = $compare ?: 'what ? is this';
$nested = $a ? ( $b ?: 1 ) : 2; // @mago-expect lint:generic/disallow-short-ternary
