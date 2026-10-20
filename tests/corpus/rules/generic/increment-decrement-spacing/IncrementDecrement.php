<?php

$i = 10;
--$i;
// @mago-expect lint:generic/increment-decrement-spacing
-- $i;
// @mago-expect lint:generic/increment-decrement-spacing
-- /*comment*/ $i;
++$i;
// @mago-expect lint:generic/increment-decrement-spacing
++ 
   $i;
// @mago-expect lint:generic/increment-decrement-spacing
++/*comment*/$i;

$i--;
// @mago-expect lint:generic/increment-decrement-spacing
$i        --;
// @mago-expect lint:generic/increment-decrement-spacing
$i /*comment*/ --;
$i++;
// @mago-expect lint:generic/increment-decrement-spacing
$i ++;
// @mago-expect lint:generic/increment-decrement-spacing
$i /*comment*/ ++;

// Handle properties and array access too.
$i['key']++;
// @mago-expect lint:generic/increment-decrement-spacing
$i['key'] ++;
$i['key']['id']++;
// @mago-expect lint:generic/increment-decrement-spacing
$i['key']['id'] ++;

$obj->prop++;
// @mago-expect lint:generic/increment-decrement-spacing
$obj->prop ++;
// @mago-expect lint:generic/increment-decrement-spacing
$obj?->prop ++;

$obj->obj->prop++;
// @mago-expect lint:generic/increment-decrement-spacing
$obj->obj->prop ++;
// @mago-expect lint:generic/increment-decrement-spacing
$obj?->obj->prop ++;

$obj->prop['key']++;
// @mago-expect lint:generic/increment-decrement-spacing
$obj->prop['key'] ++;

--ClassName::$prop;
// @mago-expect lint:generic/increment-decrement-spacing
-- ClassName::$prop;

getObject()->count
// @mago-expect lint:generic/increment-decrement-spacing
++;
getObject()->count++;
// @mago-expect lint:generic/increment-decrement-spacing
++ getObject()->count;
++getObject()->count;
