<?php

declare(strict_types=1);

// normalized_casts_are_allowed
$a = (float) 1;
$b = (string) 1;
$c = (array) 1;
$d = (object) 1;
$e = (int) 1;

// double_cast_is_flagged
// @mago-expect lint:wordpress/type-casts
$f = (double) 1;
// @mago-expect lint:wordpress/type-casts
$g = (real) 1;

// double_cast_with_whitespace_is_flagged
// @mago-expect lint:wordpress/type-casts
$h = ( double ) 1;
// @mago-expect lint:wordpress/type-casts
$i = ( real ) 1;

// normalized_casts_with_whitespace_are_allowed
$j = ( float) 1;
$k = (string ) 1;

// The "(unset)" cast has no fixture here: mago's own semantics pass
// unconditionally reports it as a hard error on every supported PHP
// version (it was removed in PHP 8.0, mago's minimum), and that report
// cannot be suppressed with `@mago-expect`. Verified directly instead
// with `mago lint --only wordpress/type-casts` against a scratch file.

// binary_cast_is_flagged
// @mago-expect lint:wordpress/type-casts
$n = (binary) 'x';

// binary_cast_with_whitespace_is_flagged
// @mago-expect lint:wordpress/type-casts
$o = ( binary ) 'x';

// binary_string_literal_is_flagged
// @mago-expect lint:wordpress/type-casts
$p = b'binary string';
// @mago-expect lint:wordpress/type-casts
$q = b"binary string";
// @mago-expect lint:wordpress/type-casts
$q2 = B'binary string';

$string = 'x';
// @mago-expect lint:wordpress/type-casts
$r = b"binary $string";

// ordinary_string_literal_is_allowed
$s = 'not binary';
$t = "not binary";
