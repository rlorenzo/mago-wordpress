<?php

declare(strict_types=1);

class Foo {
	public $bar;
	public static $baz = 'Foo';
}

$a = new Foo();
$b = new Foo( 1 );
$c = new class {};
$d = new ( 'Foo' );

// @mago-expect lint:generic/class-instantiation
$e = new Foo;

// @mago-expect lint:generic/class-instantiation
$f = new \Foo\Bar;

// @mago-expect lint:generic/class-instantiation
$g = new $a;

// @mago-expect lint:generic/class-instantiation
$h = new $a->bar;

// @mago-expect lint:generic/class-instantiation
$i = new static;

// @mago-expect lint:generic/class-instantiation
$j = new Foo::$baz;

$k = new $a->bar();
$l = new $a::$baz( 1 );
