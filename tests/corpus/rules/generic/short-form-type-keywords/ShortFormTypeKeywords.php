<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/short-form-type-keywords
$a = (boolean) $x;
// @mago-expect lint:generic/short-form-type-keywords
$b = (Integer) $x;
// @mago-expect lint:generic/short-form-type-keywords
$c = ( INTEGER ) $x;

$d = (bool) $x;
$e = (int) $x;
$f = (string) $x;
