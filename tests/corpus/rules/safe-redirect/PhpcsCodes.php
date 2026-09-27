<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
wp_redirect($url);

// @mago-expect lint:wordpress/safe-redirect
// phpcs:ignore WordPress.Security.SafeRedirect.wp_safe_redirect_wp_redirect
wp_redirect($url);

