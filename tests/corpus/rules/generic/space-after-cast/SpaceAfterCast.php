<?php

$var = (int) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (int)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (int)  $var2;

$var = (integer) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (integer)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (integer)  $var2;

$var = (string) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (string)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (string)    $var2;

$var = (float) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (float)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (float)  $var2;

$var = (double) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (double)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (double)  $var2;

$var = (real) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (real)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (real)  $var2;

$var = (array) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (array)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (array)  $var2;

$var = (bool) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (bool)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (bool)  $var2;

$var = (boolean) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (boolean)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (boolean)  $var2;

$var = (object) $var2;
// @mago-expect lint:generic/space-after-cast
$var = (object)$var2;
// @mago-expect lint:generic/space-after-cast
$var = (object)  $var2;


$var = b"binary $foo";
$var = b"binary string";
$var = b'binary string';
$var = (binary) $string;
// @mago-expect lint:generic/space-after-cast
$var = (binary)$string;

// @mago-expect lint:generic/space-after-cast
$var = (boolean) /* comment */ $var2;

// @mago-expect lint:generic/space-after-cast
$var = (int)
	$var2;

// @mago-expect lint:generic/space-after-cast
if ( (string) // phpcs:ignore Standard.Cat.SniffName -- for reasons.
    $x === 'test'
) {}

