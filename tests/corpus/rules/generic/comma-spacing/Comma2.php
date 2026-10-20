<?php

/*
 * Tests specifically for the error code differentiation.
 *
 * Each line should yield one of each of these errors: SpaceBefore, TooMuchSpaceAfter, NoSpaceAfter.
 */

// *InDeclare suffix.
// @mago-expect lint:generic/comma-spacing(3)
declare(strict_types=1 ,  encoding='utf8',ticks=0);

// *InFunctionDeclaration suffix.
function name($param1 ,   $param2,$param3) {}
$closure = function ($param1 ,   $param2,$param3) {};
$arrow = fn ($param1 ,   $param2,$param3) => $param1 * $param2 + $param3;
class Foo {
    public function name($param1 ,   $param2,$param3) {}
}

// *InClosureUse suffix.
$closure = function () use($param1 ,   $param2,$param3) {};

// *InFunctionCall suffix.
do_something($param1 ,   $param2,$param3);
$obj = new Foo($param1 ,   $param2,$param3);
$obj = new self($param1 ,   $param2,$param3);
$obj = new parent($param1 ,   $param2,$param3);
$obj = new static($param1 ,   $param2,$param3);
$anonClass = new class($param1 ,   $param2,$param3) {};
$var($param1 ,   $param2,$param3);
#[MyAttribute(1 ,  'foo',false)]
function name() {}
isset($item1 ,   $item2,$item3);
unset($item1 ,   $item2,$item3);

// No suffix.
// @mago-expect lint:generic/comma-spacing(3)
$a = array($item1 ,   $item2,$item3);
// @mago-expect lint:generic/comma-spacing(3)
$a = [$item1 ,   $item2,$item3];

// @mago-expect lint:generic/comma-spacing(3)
list($item1 ,   $item2,$item3) = $array;
// @mago-expect lint:generic/comma-spacing(3)
[$item1 ,   $item2,$item3] = $array;

// @mago-expect lint:generic/comma-spacing(6)
for ($i = 0 ,  $j = 1,$k = 2; $i < $j && $j < $k; $i++ ,  $j++,$k++) {}

// @mago-expect lint:generic/comma-spacing(3)
echo $item1 ,   $item2,$item3;

// @mago-expect lint:generic/comma-spacing(3)
use Vendor\Package\{NameA ,   NameB,NameC};

class Multi {
// @mago-expect lint:generic/comma-spacing(3)
    const CONST_A = 1 ,  CONST_B = 2,CONST_C = 3;
// @mago-expect lint:generic/comma-spacing(3)
    public $propA = 1 ,  $propB = 2,$propC = 3;

    public function name() {
// @mago-expect lint:generic/comma-spacing(3)
        global $var1 ,   $var2,$var3;
// @mago-expect lint:generic/comma-spacing(3)
        static $localA = 1 ,   $localB,$localC = 'foo';
    }
}

$value = match($value) {
// @mago-expect lint:generic/comma-spacing(3)
    1  ,   2,3 => 123,
};

// Parse error, but not our concern.

// *InAttribute suffix.
// @mago-expect lint:generic/comma-spacing(3)
#[AttributeOne ,  AttributeTwo,AttributeThree]
function hasAttribute() {}
