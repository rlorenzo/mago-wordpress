<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.ClassNameCase.Incorrect
$db = new WPDB();

// @mago-expect lint:wordpress/class-name-case
// phpcs:ignore WordPress.WP.ClassNameCase.Found
$db = new WPDB();

