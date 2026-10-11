<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/disallow-mixed-group-use
use Foo\{ClassName, function helper, const LIMIT};
// @mago-expect lint:generic/disallow-mixed-group-use
use Foo\{OtherClass, function other_helper};

// One kind of construct in a group, or no group, is fine.
use Foo\{First, Second};
use function Foo\{one, two};
use const Foo\{A, B};
use Foo\Single;
use Foo\Plain, Foo\Other;
