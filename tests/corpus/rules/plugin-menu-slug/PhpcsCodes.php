<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.Security.PluginMenuSlug.Using__FILE__
add_menu_page('Title', 'Menu', 'manage_options', __FILE__);

// @mago-expect lint:wordpress/plugin-menu-slug
// phpcs:ignore WordPress.Security.PluginMenuSlug.Found
add_menu_page('Title', 'Menu', 'manage_options', __FILE__);

