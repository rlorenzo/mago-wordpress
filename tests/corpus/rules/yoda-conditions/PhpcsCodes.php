<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
if ($value === true) {
}

// @mago-expect lint:wordpress/yoda-conditions
// phpcs:ignore WordPress.PHP.YodaConditions.Found
if ($value === true) {
}

