<?php

declare(strict_types=1);

// Comparisons without an expectation must be silenced by a phpcs comment.

// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
if ($value === true) {
}

if ($value === true) { // phpcs:ignore WordPress.PHP
}

// @mago-expect lint:wordpress/yoda-conditions
if ($value === true) { // phpcs:ignore WordPress.WP
}

/* phpcs:disable WordPress.PHP.YodaConditions */
if ($value === true) {
}
/* phpcs:enable WordPress.PHP.YodaConditions */

// @mago-expect lint:wordpress/yoda-conditions
if ($value === true) {
}

// @codingStandardsIgnoreStart
if ($value === true) {
}
// @codingStandardsIgnoreEnd
