<?php

namespace Rules\UseKeywords;

use Foo\Bar;
use Foo\Bar as Baz;
use function Foo\bar;
use function Foo\bar as baz;
use const Foo\BAR;
use Foo\{A, B as C, function d, const E};

// @mago-expect lint:generic/use-statement-keyword-spacing
use Foo\Bar  as Baz;
// @mago-expect lint:generic/use-statement-keyword-spacing
use Foo\Bar as  Baz;
// @mago-expect lint:generic/use-statement-keyword-spacing(2)
use Foo\Bar   as   Baz;
// @mago-expect lint:generic/use-statement-keyword-spacing
use function  Foo\bar;
// @mago-expect lint:generic/use-statement-keyword-spacing
use const	Foo\BAR;
// @mago-expect lint:generic/use-statement-keyword-spacing
use function
	Foo\bar;
// @mago-expect lint:generic/use-statement-keyword-spacing
use Foo\{A, function  d};
// @mago-expect lint:generic/use-statement-keyword-spacing(2)
use Foo\{A as  B, const  E};

// Closures and traits are not imports.
$closure = function () use ( $value ) {};

class UsesTrait
{
	use SomeTrait  {
		foo  as  bar;
	}
}
// @mago-expect lint:generic/use-statement-keyword-spacing
use Foo\ {A, function  d};
