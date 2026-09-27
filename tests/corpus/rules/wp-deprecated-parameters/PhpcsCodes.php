<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_the_authorParam1Found
$author = get_the_author('deprecated');

// @mago-expect lint:wordpress/wp-deprecated-parameters
// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_the_authorParam2Found
$author = get_the_author('deprecated');

