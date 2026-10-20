<?php

$ok = new class() {};
$ok = new class( 1 ) {};
$ok = new class {};

// @mago-expect lint:generic/anon-class-keyword-spacing
$bad = new class () {};
// @mago-expect lint:generic/anon-class-keyword-spacing
$bad = new class   ( $arg ) extends Foo {};
// @mago-expect lint:generic/anon-class-keyword-spacing
$bad = new class
(
	$arg
) {};
// @mago-expect lint:generic/anon-class-keyword-spacing
$bad = new class /* comment */ () {};
