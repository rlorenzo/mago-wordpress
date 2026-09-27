<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.DeprecatedFunctions.get_currentuserinfoFound
get_currentuserinfo();

// @mago-expect lint:wordpress/wp-deprecated-functions
// phpcs:ignore WordPress.WP.DeprecatedFunctions.get_userdatabyloginFound
get_currentuserinfo();

