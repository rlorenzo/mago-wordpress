<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
echo '<script src="https://example.com/app.js"></script>';

// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
echo '<link rel="stylesheet" href="https://example.com/app.css" />';

// @mago-expect lint:wordpress/enqueued-resources
// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
echo '<script src="https://example.com/app.js"></script>';

