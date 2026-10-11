<?php

declare(strict_types=1);

namespace Foo\Bar;

use A\B;
use function A\b;

// @mago-expect lint:generic/file-header
use A\C;

echo B::class, C::class;
