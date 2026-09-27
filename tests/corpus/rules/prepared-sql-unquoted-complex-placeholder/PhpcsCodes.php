<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
$wpdb->prepare('SELECT * FROM my_table WHERE name = %1$s', $name);

// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.QuotedSimplePlaceholder
$wpdb->prepare('SELECT * FROM my_table WHERE name = %1$s', $name);

