<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.Reserved
register_post_type('author', []);

// phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.TooLong
register_post_type('my-own-post-type-too-long', []);

// @mago-expect lint:wordpress/valid-post-type-slug
// phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.Reserved
register_post_type('my-own-post-type-too-long', []);

