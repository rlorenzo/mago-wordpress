<?php

function ok() {
	return 1;
}

function empty_function() {}

function brace_on_new_line()
// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
{
	return 1;
}

function brace_on_new_line_after_comment() // comment
// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
{
	return 1;
}

// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
function no_space_before_brace(){
	return 1;
}

// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
function two_spaces_before_brace()  {
	return 1;
}

// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
function tab_before_brace()	{
	return 1;
}

function with_return_type(): int
// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
{
	return 1;
}

// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
function with_return_type_no_space(): int{
	return 1;
}

// Closures are not checked by WordPress-Core.
$closure = function ()
{
	return 1;
};

abstract class Foo {
	public function method()
// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
	{
		return 1;
	}

// @mago-expect lint:generic/opening-function-brace-kernighan-ritchie
	public function other(){
		return 1;
	}

	abstract public function nobody();
}
