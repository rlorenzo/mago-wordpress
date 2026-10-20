<?php

// A pragma before a declaration covers its closing brace, which phpcs reports.

function ok() {
	return 1;
}

// @mago-expect lint:generic/function-closing-brace
function blank_line() {
	return 1;

}

// @mago-expect lint:generic/function-closing-brace
function many_blank_lines() {
	return 1;


}

// @mago-expect lint:generic/function-closing-brace
function after_comment() {
	return 1;
	// Done.

}

// @mago-expect lint:generic/function-closing-brace
$closure = function () {
	return 1;

};

abstract class Foo {
	// @mago-expect lint:generic/function-closing-brace
	public function method() {
		return 1;

	}

	// @mago-expect lint:generic/function-closing-brace
	public function empty_with_blank() {

	}

	abstract public function nobody();

	public function same_line() { return 1; }
}
