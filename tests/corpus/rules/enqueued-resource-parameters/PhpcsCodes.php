<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
wp_enqueue_script('my-script', 'https://example.com/app.js', [], false, true);

// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
wp_enqueue_script('my-script', 'https://example.com/app.js', [], null, true);

// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
wp_enqueue_script('my-script', 'https://example.com/app.js', [], '1.0');

// @mago-expect lint:wordpress/enqueued-resource-parameters
// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
wp_enqueue_script('my-script', 'https://example.com/app.js', [], false, true);

