<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.QuotedSimplePlaceholder
$wpdb->prepare("SELECT * FROM my_table WHERE name = '%s'", $name);

// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnnecessaryPrepare
$wpdb->prepare('SELECT * FROM my_table');

// @mago-expect lint:wordpress/prepared-sql-placeholders
// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnnecessaryPrepare
$wpdb->prepare("SELECT * FROM my_table WHERE name = '%s'", $name);

