<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.Capabilities.Unknown
current_user_can('unknown_cap');

// phpcs:ignore WordPress.WP.Capabilities.RoleFound
current_user_can('administrator');

// phpcs:ignore WordPress.WP.Capabilities.Deprecated
current_user_can('level_10');

// @mago-expect lint:wordpress/capabilities-warning
// phpcs:ignore WordPress.WP.Capabilities.RoleFound
current_user_can('unknown_cap');

