<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase
do_action('Myplugin_saved');

// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
do_action('myplugin-saved');

// @mago-expect lint:wordpress/valid-hook-name-warning
// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase
do_action('myplugin-saved');

