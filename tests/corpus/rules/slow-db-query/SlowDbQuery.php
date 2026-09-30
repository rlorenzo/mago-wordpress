<?php

declare(strict_types=1);

// meta_query_key_is_flagged
$query = new WP_Query([
    // @mago-expect lint:wordpress/slow-db-query
    'meta_query' => [
        ['key' => 'featured', 'value' => 'yes'],
    ],
]);

// tax_query_key_is_flagged
$args = [
    // @mago-expect lint:wordpress/slow-db-query
    'tax_query' => [
        ['taxonomy' => 'genre', 'field' => 'slug', 'terms' => 'jazz'],
    ],
];

// meta_key_in_legacy_array_is_flagged
// @mago-expect lint:wordpress/slow-db-query
$args = array('meta_key' => 'color', 'post_type' => 'product');

// meta_value_key_is_flagged
// @mago-expect lint:wordpress/slow-db-query
$args = ['meta_value' => 'blue'];

// nested_meta_query_is_flagged
$args = [
    'post_type' => 'post',
    'inner' => [
        // @mago-expect lint:wordpress/slow-db-query
        'meta_query' => [],
    ],
];

// regular_query_args_are_allowed
$query = new WP_Query([
    'post_type' => 'product',
    'category_name' => 'featured',
    'posts_per_page' => 20,
]);

// meta_query_as_value_is_allowed
$keys = ['meta_query', 'tax_query'];

// variable_key_is_allowed
$args = [$key => 'value'];

// array_key_assignment_is_flagged
// @mago-expect lint:wordpress/slow-db-query
$args['meta_key'] = 'color';

// coalesce_array_key_assignment_is_flagged
// @mago-expect lint:wordpress/slow-db-query
$args['tax_query'] ??= [];

// array_key_read_is_allowed
$color = $args['meta_key'];

// query_string_reports_each_slow_key
// @mago-expect lint:wordpress/slow-db-query
// @mago-expect lint:wordpress/slow-db-query
$query = 'foo=bar&meta_key=foo&meta_value=bar';

// query_string_with_empty_values_is_flagged
// @mago-expect lint:wordpress/slow-db-query
// @mago-expect lint:wordpress/slow-db-query
$query = 'foo=bar&meta_key=&meta_value=';

// query_string_without_slow_keys_is_allowed
$query = 'foo=bar&post_type=page';
