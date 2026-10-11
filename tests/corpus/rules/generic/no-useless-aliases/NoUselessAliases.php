<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/no-useless-aliases
use Foo\Bar as Bar;
// @mago-expect lint:generic/no-useless-aliases
use Foo\Baz as BAZ;
// @mago-expect lint:generic/no-useless-aliases
use function Foo\strlen as STRLEN;
// @mago-expect lint:generic/no-useless-aliases
use const Foo\VALUE as VALUE;
// @mago-expect lint:generic/no-useless-aliases
use Foo\{Alpha as Alpha, Beta as Gamma};

// A different alias is fine, and so is a constant alias in another case.
use Foo\Qux as Quux;
use const Foo\OTHER as other;
use Foo\Plain;
