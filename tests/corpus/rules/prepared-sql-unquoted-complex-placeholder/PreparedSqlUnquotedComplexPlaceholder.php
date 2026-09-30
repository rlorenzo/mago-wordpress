<?php

declare(strict_types=1);

// unquoted_numbered_placeholder
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare('SELECT * FROM my_table WHERE name = %1$s', $name);

// unquoted_padded_placeholder
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare('SELECT * FROM my_table WHERE code = %05s', $code);

// unquoted_custom_padding_placeholder
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare("SELECT * FROM my_table WHERE code = %'.10s", $code);

// unquoted_precision_placeholder
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare('SELECT * FROM my_table WHERE price = %.2f', $price);

// unquoted_placeholder_in_interpolated_string
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_title = %1\$s", $title);

// unquoted_placeholder_in_concatenation
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare('SELECT * FROM ' . $table . ' WHERE ID = %2$d AND name = %1$s', $name, $id);

// nullsafe_and_named_query_argument
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb?->prepare(query: 'SELECT * FROM my_table WHERE name = %1$s', args: $name);

// quoted_complex_placeholders_are_ok
$wpdb->prepare('SELECT * FROM my_table WHERE name = "%1$s" AND slug = \'%2$s\'', $name, $slug);
$wpdb->prepare("SELECT * FROM my_table WHERE code = '%05s'", $code);

// simple_placeholders_are_ok
$wpdb->prepare('SELECT * FROM my_table WHERE name = %s AND ID = %d AND price = %f AND rate = %F', $name, $id, $price, $rate);

// identifier_placeholders_are_ok
$wpdb->prepare('SELECT * FROM %i WHERE %1$i = %d', $table, $id);

// literal_percent_is_not_a_placeholder
$wpdb->prepare('SELECT * FROM my_table WHERE rate LIKE %s AND note = "100%%1$s"', $rate);
$wpdb->prepare('SELECT * FROM my_table WHERE ID = %d AND pct = 100%%', $id);

// other_receivers_and_methods_are_ignored
$db->prepare('SELECT * FROM my_table WHERE name = %1$s', $name);
$wpdb->query('SELECT * FROM my_table WHERE name = %1$s');

// quoted_custom_padding_placeholder_is_ok
$wpdb->prepare("SELECT * FROM my_table WHERE code = '%'.10s' AND name = \"%1\$'x5s\"", $code);

// like_wildcards_are_not_placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE code LIKE 'a%5sb' AND name = %s", $name);

// unquoted_placeholder_after_like_is_reported
// @mago-expect lint:wordpress/prepared-sql-unquoted-complex-placeholder
$wpdb->prepare("SELECT * FROM my_table WHERE code LIKE %s AND name = %'.5s", $code, $name);
