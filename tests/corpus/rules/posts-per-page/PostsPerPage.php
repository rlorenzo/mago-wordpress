<?php

declare(strict_types=1);

// posts_per_page_minus_one_is_allowed (WPCS only checks value > limit)
$query = new WP_Query(['posts_per_page' => -1]);
$query = new WP_Query(['posts_per_page' => (-1)]);
$query = new WP_Query(['posts_per_page' => '-1']);

// parenthesized_over_limit_is_flagged
// @mago-expect lint:wordpress/posts-per-page
$query = new WP_Query(['posts_per_page' => (500)]);

// posts_per_page_over_limit_is_flagged
// @mago-expect lint:wordpress/posts-per-page
$query = new WP_Query(['posts_per_page' => 500]);

// posts_per_page_over_limit_string_is_flagged
// @mago-expect lint:wordpress/posts-per-page
$query = new WP_Query(['posts_per_page' => '500']);

// numberposts_over_limit_is_flagged
// @mago-expect lint:wordpress/posts-per-page
$posts = get_posts(array('numberposts' => 500));

// numberposts_minus_one_is_allowed
$posts = get_posts(array('numberposts' => -1));

// nopaging_is_allowed (WPCS moved the nopaging check out of this sniff in 1.0)
$query = new WP_Query(['nopaging' => true]);

// posts_per_page_numeric_separator_over_limit_is_flagged
// @mago-expect lint:wordpress/posts-per-page
$query = new WP_Query(['posts_per_page' => 1_000]);

// custom_limit_is_respected: skipped. The Rust rule's `max-posts-per-page`
// option has no matching `Settings` field, so the limit cannot be lowered
// for a corpus fixture (the corpus shares one composer.json for every rule).

// reasonable_posts_per_page_is_allowed
$query = new WP_Query(['posts_per_page' => 20, 'paged' => 2]);

// limit_boundary_is_allowed
$query = new WP_Query(['posts_per_page' => 100]);

// variable_value_is_allowed
$query = new WP_Query(['posts_per_page' => $limit]);

// negative_max_falls_back_to_default: skipped, same reason as custom_limit_is_respected.
// negative_max_still_flags_over_default: skipped, same reason as custom_limit_is_respected.
// raised_limit_is_respected: skipped, same reason as custom_limit_is_respected.
