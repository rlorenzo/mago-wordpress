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
$args = ['post_type' => 'post', 'inner' => [
    // @mago-expect lint:wordpress/slow-db-query
    'meta_query' => [],
]];

// set_query_var_with_meta_key_is_flagged
// @mago-expect lint:wordpress/slow-db-query
set_query_var('meta_key', 'color');

// fully_qualified_set_query_var_is_flagged
// @mago-expect lint:wordpress/slow-db-query
\set_query_var('meta_query', []);

// regular_query_args_are_allowed
$query = new WP_Query([
    'post_type' => 'product',
    'category_name' => 'featured',
    'posts_per_page' => 20,
]);

// meta_query_as_value_is_allowed
$keys = ['meta_query', 'tax_query'];

// set_query_var_with_safe_key_is_allowed
set_query_var('paged', 2);

// variable_key_is_allowed
$args = [$key => 'value'];
