<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

$args = [
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
    'tax_query' => [],
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
    'meta_key' => 'color',
    // @mago-expect lint:wordpress/slow-db-query
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
    'meta_query' => [],
];
