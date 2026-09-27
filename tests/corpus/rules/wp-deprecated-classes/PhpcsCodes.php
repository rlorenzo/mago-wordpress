<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.DeprecatedClasses.wp_user_searchFound
$search = new WP_User_Search();

// @mago-expect lint:wordpress/wp-deprecated-classes
// phpcs:ignore WordPress.WP.DeprecatedClasses.WP_User_SearchFound
$search = new WP_User_Search();

