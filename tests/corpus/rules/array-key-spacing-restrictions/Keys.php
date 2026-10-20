<?php

// Variable keys.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
foo( $arr[$test] ); // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
bar( $arr[ $test] ); // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
foo( $arr[$test ] ); // Bad.
bar( $arr[ $test ] ); // Good.

// The opposite with strings.
foo( $arr['test'] ); // Good.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
bar( $arr[ 'test'] ); // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
foo( $arr['test' ] ); // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
bar( $arr[ 'test' ] ); // Bad.

// Nested ones.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
foo( $arr[ $test[$taz] ] ); // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
bar( $arr[ $test[ 'taz' ] ] ); // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
// @mago-expect lint:wordpress/array-key-spacing-restrictions
foo( $arr[$test[ 'taz' ]] ); // Bad.
bar( $arr[ $test['taz'] ] ); // Good.

// Mixed key.
foo( $arr[ 'string' . $var ] ); // Good, should have spaces.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
bar( $arr['string'.$var] ); // Bad.

// Non-string/int.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[FooClass::FOO_KEY]; // Bad.

// Space size check.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
// @mago-expect lint:wordpress/array-key-spacing-restrictions
bar( $arr[     $test    ] ); // Bad x 2.

// @mago-expect lint:wordpress/array-key-spacing-restrictions
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$a = $arr[    /*comment*/ 'key']; // Bad x 2.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$a = $arr[/*comment*/ 'key'   ]; // Bad x 2.

// Verify handling of array assignments without an explicit key.
$bar[] = 10; // Good.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$bar[   ] = 10; // Bad.

// Verify handling of array assignments without an explicit key, but with a comment.
$bar[ /*comment*/ ] = 10; // Good.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$bar[/*comment*/] = 10; // Bad.

// Integer keys.
$arr[0]; // Good.
$arr[-1]; // Good.
$arr[+1]; // Good.

// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 0 ]; // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ -1 ]; // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ +1 ]; // Bad.

// Verify key calculations are handled correctly.
$arr[ 10 + 5 ]; // Good.
$arr[ 5-2 - -1 ]; // Good.

// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 10 + 5]; // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[5-2 - -1 ]; // Bad.

// Verify non-decimal integer keys are handled correctly.
$arr[012]; // Good.
$arr[0b01]; // Good.
$arr[0xCAFE]; // Good.

// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 012 ]; // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 0b01 ]; // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 0xCAFE ]; // Bad.

// Ensure correct handling of PHP 7.4+ numeric literals with underscores.
$arr[1_000_000]; // Good.
$arr[0xCAFE_F00D]; // Good.

// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 1_000_000 ]; // Bad.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 0xCAFE_F00D ]; // Bad.

// Ensure correct handling of PHP 8.1+ octal literals.
$arr[0o137]; // Good.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$arr[ 0o137 ]; // Bad.
