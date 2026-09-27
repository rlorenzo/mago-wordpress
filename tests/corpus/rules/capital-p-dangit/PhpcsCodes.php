<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
echo 'Welcome to Wordpress';

// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInComment
// Built for Wordpress.

// @mago-expect lint:wordpress/capital-p-dangit
// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInComment
echo 'Welcome to Wordpress';
