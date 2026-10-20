<?php

// @mago-expect lint:wordpress/cast-structure-spacing
$array1 =(array)$array; // Bad.
$array2 = (array) $array; // Ok.

// @mago-expect lint:wordpress/cast-structure-spacing
$bool1 =(bool)$bool; // Bad.
$bool2 = (bool) $bool; // Ok.

// @mago-expect lint:wordpress/cast-structure-spacing
$int1 =(int)$int; // Bad.
$int2 = (int) $int; // Ok.

// @mago-expect lint:wordpress/cast-structure-spacing
$obj1 =( object )$object; // Bad; n.b. spacing within the cast is dealt with by an upstream sniff.
$obj2 = (object) $object; // Ok.

// @mago-expect lint:wordpress/cast-structure-spacing
$str1 =(string)$string; // Bad.
$str2 = (string) $string; // Ok.

// @mago-expect lint:wordpress/cast-structure-spacing
$unset1 =( object)$unset; // Bad; n.b. spacing within the cast is dealt with by an upstream sniff.

// @mago-expect lint:wordpress/cast-structure-spacing
$float1 =(float )$float; // Bad; n.b. spacing within the cast is dealt with by an upstream sniff.
$float2 = (float) $float; // Ok.

function_call( ...(array) $mixed ); // OK.
