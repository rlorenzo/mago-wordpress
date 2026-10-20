<?php

// Valid.
function ok( $a, $b = 1, &$c = array(), ...$d ) {}
function typed( int $a, ?string $b = null, array|bool $c = false ) {}
function none() {}
$closure = function ( $a ) use ( $b, &$c ) {};
$arrow = fn( $a ) => $a;
class Foo {
	public function __construct( public int $a, protected readonly string $b = '' ) {}
}

// Inside the parentheses.
// @mago-expect lint:generic/function-declaration-argument-spacing(2)
function no_spaces($a, $b) {}
// @mago-expect lint:generic/function-declaration-argument-spacing(2)
function two_spaces(  $a, $b  ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function only_after( $a, $b) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function only_before($a, $b ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function empty_with_space( ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function empty_with_spaces(   ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing(4)
$closure = function ($a) use ($b) {};
// @mago-expect lint:generic/function-declaration-argument-spacing(2)
$arrow = fn($a) => $a;

// Default values.
// @mago-expect lint:generic/function-declaration-argument-spacing(4)
function equals_no_space( $a=1, $b= 2, $c =3 ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing(2)
function equals_two_spaces( $a  =  1 ) {}
function equals_comment( $a /* c */ = 1 ) {}

// References, variadics and type hints.
// @mago-expect lint:generic/function-declaration-argument-spacing
function reference( & $a, &$b ) {}
function variadic( ... $a ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function hint_no_space( int$a ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing(2)
function hint_two_spaces( int  $a, ?string   $b ) {}

// Commas.
// @mago-expect lint:generic/function-declaration-argument-spacing
function comma_before( $a , $b ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function comma_no_space_after( $a,$b ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function comma_two_spaces_after( $a,  $b ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function comma_no_space_before_hint( int $a,int $b ) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function multi_line(
	$a ,
	$b,
	$c
) {}
// @mago-expect lint:generic/function-declaration-argument-spacing
function multi_line_comma_start(
	$a
	, $b
) {}

// Constructor property promotion.
class Bar {
// @mago-expect lint:generic/function-declaration-argument-spacing(3)
	public function __construct( public  int $a, private readonly  string $b,protected int $c ) {}
}
