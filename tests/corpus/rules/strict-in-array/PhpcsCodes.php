<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
$found = in_array($needle, $haystack);

// phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
$found = in_array($needle, $haystack, false);

// @mago-expect lint:wordpress/strict-in-array
// phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
$found = in_array($needle, $haystack);

// @mago-expect lint:wordpress/strict-in-array
// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
$found = in_array($needle, $haystack, false);

