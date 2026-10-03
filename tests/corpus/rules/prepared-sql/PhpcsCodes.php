<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query('SELECT * FROM t WHERE a = ' . $value);

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("SELECT * FROM t WHERE a = '$value'");

// @mago-expect lint:wordpress/prepared-sql
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query('SELECT * FROM t WHERE a = ' . $value);
