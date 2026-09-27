<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

$query = new WP_Query([
    // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
    'posts_per_page' => 500,
    // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts
    'numberposts' => 500,
]);

$query = new WP_Query([
    // @mago-expect lint:wordpress/posts-per-page
    // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
    'numberposts' => 500,
]);
