<?php

declare(strict_types=1);

// Ported from WPCS Tests/WP/CapabilitiesUnitTest.1.inc. The corpus sets
// custom-capabilities to ["custom_cap", "foo_bar"].

if (author_can($post, 'read')) {
}
map_meta_cap('edit_posts', $user->ID);

// Undetermined values are not checked.
add_posts_page('page_title', 'menu_title', 'admin' . 'istrator', 'menu_slug', 'function');
if (author_can($post, $capability)) {
}
add_submenu_page('parent_slug', 'page_title', 'menu_title', $variable, 'menu_slug', 'function');
add_menu_page($pagetitle, $menu_title, $subscriber, 'handle', 'function', 'icon_url');
add_options_page($pagetitle, $menu_title, CONSTANT, 'menu_slug', 'function');
add_posts_page('page_title', 'menu_title', self /* comment */::CAPABILITY, 'menu_slug', 'function');
add_menu_page($p, $t, $caps['level']);
add_menu_page($p, $t, "{$role}");
add_menu_page(...$args);

// Empty capability.
// @mago-expect lint:wordpress/capabilities
if (author_can($post, '')) {
}

// Deprecated capabilities.
// @mago-expect lint:wordpress/capabilities
if (author_can($post, 'level_5')) {
}
// @mago-expect lint:wordpress/capabilities
add_options_page('page_title', 'menu_title', 'level_10', 'menu_slug', 'function');

// Unknown capabilities.
// @mago-expect lint:wordpress/capabilities
if (author_can($post, 'unknown_cap')) {
}
// @mago-expect lint:wordpress/capabilities
if (current_user_can('bar_foo')) {
}
// @mago-expect lint:wordpress/capabilities
if (current_user_can_for_blog('3', 'unknown_cap')) {
}
// @mago-expect lint:wordpress/capabilities
add_users_page('page_title', 'menu_title', 'bar_foo', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_management_page('page_title', 'menu_title', "bar_foo", 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_menu_page($pagetitle, 'menu_title', 'bar_foo', 'handle', 'function', 'icon_url');
// @mago-expect lint:wordpress/capabilities
if (author_can($post, 'custom_capability')) {
}
// @mago-expect lint:wordpress/capabilities
if (current_user_can('Manage_Options')) {
}

// Roles found instead of capabilities.
// @mago-expect lint:wordpress/capabilities
add_posts_page('page_title', 'menu_title', 'administrator', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
\aDd_MeDiA_pAgE('page_title', 'menu_title', 'editor', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
ADD_PAGES_PAGE('page_title', 'menu_title', 'author', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_comments_page('page_title', 'menu_title', 'contributor', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_theme_page('page_title', $menu_title, 'subscriber', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_plugins_page('page_title', 'menu_title', 'super_admin', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_users_page('page_title', 'menu_title', 'administrator', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
add_management_page('page_title', 'menu_title', 'editor', 'menu_slug', 'function');
// @mago-expect lint:wordpress/capabilities
if (current_user_can('super_admin')) {
}
// @mago-expect lint:wordpress/capabilities
if (current_user_can_for_blog('1', 'editor')) {
}
add_dashboard_page(
    'page_title',
    'menu_title',
    // @mago-expect lint:wordpress/capabilities
    'super_admin' /* Comment */,
    'menu_slug',
    'function',
);
// @mago-expect lint:wordpress/capabilities
map_meta_cap('editor', $user->ID);
// @mago-expect lint:wordpress/capabilities
if (user_can($user, 'author')) {
}

// Named arguments.
// @mago-expect lint:wordpress/capabilities
add_menu_page(capability: 'foobar', page_title: $p, menu_title: $m);
// @mago-expect lint:wordpress/capabilities
add_submenu_page('parent', 'page', 'menu', 'administrator');

// Custom capabilities from the settings.
if (current_user_can('foo_bar')) {
}
if (author_can($post, 'custom_cap')) {
}

add_menu_page([]);

// Method calls and other namespaces are not the core functions.
$obj->current_user_can('foo_bar_baz');
My\NamespaceS\current_user_can('administrator');
\MyNamespace\add_comments_page('page_title', 'menu_title', 'administrator', 'menu_slug', 'function');
// In the global namespace this is the core function, which WPCS cannot resolve yet.
// @mago-expect lint:wordpress/capabilities
namespace\author_can($post, 'administrator');
namespace\Sub\add_posts_page('page_title', 'menu_title', 'administrator', 'menu_slug', 'function');
