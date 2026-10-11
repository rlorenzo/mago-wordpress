<?php

$aligned = array(
	"one"            => 1,
	"three_long_key" => 2,
);

$misaligned = array(
	// @mago-expect lint:wordpress/array-alignment
	"one" => 1,
	"three_long_key" => 2,
	// @mago-expect lint:wordpress/array-alignment
	"x"  => 3,
);

$long = array(
	"short" => 1,
	"a_really_really_really_really_really_really_long_key_here_x" => 2,
	// @mago-expect lint:wordpress/array-alignment
	"a_really_really_really_really_really_really_long_key_here_y"    => 3,
);

// @mago-expect lint:wordpress/array-alignment
$single = array( "a"   => 1, "bb" => 2 );
