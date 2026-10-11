<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/lowercase-use-function-const
use FUNCTION Foo\helper;
// @mago-expect lint:generic/lowercase-use-function-const
use Const Foo\LIMIT;
// @mago-expect lint:generic/lowercase-use-function-const(2)
use Foo\{ClassName, Function helper, CONST LIMIT};

use function Foo\fine;
use const Foo\FINE;
