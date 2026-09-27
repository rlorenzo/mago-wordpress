<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query
mysqli_query($db, 'SELECT 1');

// @mago-expect lint:wordpress/db-restricted-functions
// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_query
mysqli_query($db, 'SELECT 1');

