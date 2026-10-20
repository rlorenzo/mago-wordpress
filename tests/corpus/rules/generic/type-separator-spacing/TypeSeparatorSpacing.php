<?php

class Correct
{
	public int|bool|null $property;

	protected function foo(int|bool $a, Foo&Bar $b): Baz|false {}

}

function f(?string $a, int|string &...$p): string|int {}

$closure = function (TypeA|TypeB $p): TypeA|TypeB {};
$arrow = fn (TypeA|TypeB $p): string|int => $p;

// A multi-catch is not a type declaration.
try {
} catch (A | B $e) {
}

class Incorrect
{
	// @mago-expect lint:generic/type-separator-spacing(4)
	public int | bool    |
		null $property;

	// @mago-expect lint:generic/type-separator-spacing(2)
	protected function foo(int | bool $a) {}

	// @mago-expect lint:generic/type-separator-spacing(2)
	protected function bar(Foo &  Bar $a) {}
}

// @mago-expect lint:generic/type-separator-spacing(4)
function g(TypeA      |       TypeB $p): \Fully\Qualified & Partially\Qualified {}
