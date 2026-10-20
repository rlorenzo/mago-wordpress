<?php

declare(strict_types=1);

// @mago-expect lint:generic/deprecated-functions
echo strftime('%Y');
// @mago-expect lint:generic/deprecated-functions
$a = \utf8_encode($b);
// @mago-expect lint:generic/deprecated-functions
$c = UTF8_DECODE($d);

// A method of the same name and a current function are fine.
$obj->strftime();
Foo::utf8_encode();
echo date('Y');
