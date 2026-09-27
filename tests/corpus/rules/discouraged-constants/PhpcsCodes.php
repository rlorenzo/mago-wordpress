<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.DiscouragedConstants.STYLESHEETPATHUsageFound
echo STYLESHEETPATH;

// phpcs:ignore WordPress.WP.DiscouragedConstants.STYLESHEETPATHDeclarationFound
define('STYLESHEETPATH', '/path');

// @mago-expect lint:wordpress/discouraged-constants
// phpcs:ignore WordPress.WP.DiscouragedConstants.STYLESHEETPATHUsageFound
echo TEMPLATEPATH;

// @mago-expect lint:wordpress/discouraged-constants
// phpcs:ignore WordPress.WP.DiscouragedConstants.UsageFound
echo STYLESHEETPATH;

// @mago-expect lint:wordpress/discouraged-constants
// phpcs:ignore WordPress.WP.DiscouragedConstants.STYLESHEETPATHUsageFound
define('STYLESHEETPATH', '/path');

