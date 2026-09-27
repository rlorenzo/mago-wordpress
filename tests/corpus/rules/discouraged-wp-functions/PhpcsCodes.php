<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts
query_posts('cat=1');

// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log('debug');

// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_phpinfo
phpinfo();

// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
$data = serialize($value);

// @mago-expect lint:wordpress/discouraged-wp-functions
// phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query
query_posts('cat=1');

// @mago-expect lint:wordpress/discouraged-wp-functions
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_phpinfo
phpinfo();

