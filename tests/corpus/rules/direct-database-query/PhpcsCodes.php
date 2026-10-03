<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$count = $wpdb->get_var('SELECT COUNT(*) FROM t');

// @mago-expect lint:wordpress/direct-database-query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$count = $wpdb->get_var('SELECT COUNT(*) FROM t');

// @mago-expect lint:wordpress/direct-database-query
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->insert('t', []);
