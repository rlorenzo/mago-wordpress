<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
$db = new PDO('sqlite::memory:');

// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__pdo
$db = new pdo('sqlite::memory:');

// @mago-expect lint:wordpress/db-restricted-classes
// phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
$db = new pdo('sqlite::memory:');

