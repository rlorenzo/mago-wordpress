<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.DeprecatedParameterValues.Found
$home = get_bloginfo('home');

// @mago-expect lint:wordpress/wp-deprecated-parameter-values
// phpcs:ignore WordPress.WP.DeprecatedParameterValues.home
$home = get_bloginfo('home');

