<?php

// Valid.
function single( $a, $b ) {
	return 1;
}

function multi(
	$a,
	$b
) {
	return 1;
}

$closure = function ( $a ) use ( $b ) {
	return 1;
};

// Spacing after the keyword, before the parenthesis, around use.
// @mago-expect lint:generic/multi-line-function-declaration
function  two_spaces_after_function() {}
// @mago-expect lint:generic/multi-line-function-declaration
function space_before_paren () {}
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function() {};
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function  () {};
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function () use( $a ) {};
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function () use  ( $a ) {};
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function ()use ( $a ) {};

abstract class Foo {
// @mago-expect lint:generic/multi-line-function-declaration
	abstract public function nobody() ;
}

// A single-line declaration.
// @mago-expect lint:generic/multi-line-function-declaration
function content_after_brace() { return 1; }
// @mago-expect lint:generic/multi-line-function-declaration
function comment_after_brace() { // comment
	return 1;
}
function empty_body() { }
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function () { return 1; };
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function ()
{
	return 1;
};
// @mago-expect lint:generic/multi-line-function-declaration
$closure = function (){
	return 1;
};
// @mago-expect lint:generic/multi-line-function-declaration
function close_on_new_line( $a
) {
	return 1;
}

// A multi-line declaration.
// @mago-expect lint:generic/multi-line-function-declaration
function first_param_on_the_keyword_line( $a,
	$b
) {
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
function two_params_on_a_line(
	$a, $b
) {
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration(3)
function bad_indent(
$a,
		$b,
	$c
		) {
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
function blank_line(
	$a,

	$b
) {
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
function close_on_the_last_param_line(
	$a,
	$b ) {
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
function brace_on_new_line(
	$a,
	$b
)
{
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
function no_space_before_brace(
	$a,
	$b
){
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
function two_spaces_before_brace(
	$a,
	$b
)  {
	return 1;
}

// @mago-expect lint:generic/multi-line-function-declaration
$closure = function (
	$a,
	$b
) use (
	$c,
$d
) {
	return 1;
};

// @mago-expect lint:generic/multi-line-function-declaration(4)
$closure = function ( $a,
	$b ) use ( $c, $d
) {
	return 1;
};

function with_default(
	$a = array(
		1,
		2,
	),
	$b = null
) {
	return 1;
}
