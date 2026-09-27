<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.PHP.TypeCasts.DoubleRealFound
$f = (double) 1;

// phpcs:ignore WordPress.PHP.TypeCasts.BinaryFound
$s = (binary) $value;

// @mago-expect lint:wordpress/type-casts
// phpcs:ignore WordPress.PHP.TypeCasts.BinaryFound
$f = (double) 1;

