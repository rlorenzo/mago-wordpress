<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
$year = date('Y');

// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
date_default_timezone_set('UTC');

// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
$now = current_time('timestamp');

// @mago-expect lint:wordpress/wp-date-time
// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.RequestedUTC
$now = current_time('timestamp');

// @mago-expect lint:wordpress/wp-date-time
// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date
$year = date('Y');

