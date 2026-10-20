<?php

$ok = new class() {};
$ok = new class( 1 ) extends Foo {};

// @mago-expect lint:generic/require-anon-class-parentheses
$bad = new class {};
// @mago-expect lint:generic/require-anon-class-parentheses
$bad = new class extends Foo implements Bar {};
// @mago-expect lint:generic/require-anon-class-parentheses
$bad = new class
{
	public function foo() {}
};
