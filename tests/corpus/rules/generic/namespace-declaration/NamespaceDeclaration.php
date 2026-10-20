<?php

// @mago-expect lint:generic/namespace-declaration
namespace Rules\One;
use Foo\Bar;

// @mago-expect lint:generic/namespace-declaration
namespace Rules\Two;


use Foo\Baz;

namespace Rules\Three;

use Foo\Qux;

echo namespace\foo();
