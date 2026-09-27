<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.PHP.RestrictedPHPFunctions.create_function_create_function
create_function('$a', 'return $a;');

// @mago-expect lint:wordpress/restricted-php-functions
// phpcs:ignore WordPress.PHP.RestrictedPHPFunctions.create_function
create_function('$a', 'return $a;');

