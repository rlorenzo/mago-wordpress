<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.GetMetaSingle.Missing
$meta = get_post_meta($post_id, $meta_key);

// @mago-expect lint:wordpress/get-meta-single
// phpcs:ignore WordPress.WP.GetMetaSingle.Found
$meta = get_post_meta($post_id, $meta_key);

