<?php

declare(strict_types=1);

// file_constant_as_menu_slug_is_flagged
// @mago-expect lint:wordpress/plugin-menu-slug
add_menu_page($page_title, $menu_title, $capability, __FILE__, $function, $icon_url, $position);

// case_insensitive_function_and_magic_constant_are_flagged
// @mago-expect lint:wordpress/plugin-menu-slug
\ADD_DASHBOARD_PAGE($page_title, $menu_title, $capability, __file__, $function);

// menu_slug_string_literal_is_not_flagged
add_submenu_page($parent_slug, $page_title, $menu_title, $capability, 'awesome-submenu-page', $function);

// parent_slug_and_menu_slug_both_using_file_are_flagged_twice
// @mago-expect lint:wordpress/plugin-menu-slug
// @mago-expect lint:wordpress/plugin-menu-slug
Add_Submenu_Page(__FILE__ . 'parent', $page_title, $menu_title, $capability, __FILE__, $function);

// method_call_is_not_the_wp_core_function
$my_class->add_dashboard_page($page_title, $menu_title, $capability, __FILE__, $function);

// static_call_is_not_the_wp_core_function
Some_Class::add_dashboard_page($page_title, $menu_title, $capability, __FILE__, $function);

// namespaced_method_call_is_not_the_wp_core_function
\My_Namespace\add_dashboard_page($page_title, $menu_title, $capability, __FILE__, $function);

// named_parent_slug_argument_is_flagged
add_submenu_page(
    page_title: $page_title,
    menu_title: $menu_title,
    // @mago-expect lint:wordpress/plugin-menu-slug
    parent_slug: __FILE__,
    capability: $capability,
);

// fully_qualified_call_is_flagged
// @mago-expect lint:wordpress/plugin-menu-slug
\add_menu_page($page_title, $menu_title, $capability, __FILE__, $function, $icon_url, $position);

// namespaced_calls_are_not_flagged
MyNamespace\add_submenu_page($parent_slug, $page_title, $menu_title, $capability, __FILE__, $function);
\MyNamespace\add_dashboard_page($page_title, $menu_title, $capability, __FILE__, $function);
namespace\Sub\add_submenu_page($parent_slug, $page_title, $menu_title, $capability, __FILE__, $function);

// relative_namespace_operator_resolves_to_the_global_function_and_is_flagged
//
// Unlike the sniff, which cannot resolve `namespace\` at the token level,
// this rule sees that an unnamespaced file's `namespace\add_menu_page` is
// the global add_menu_page().
namespace\add_menu_page($page_title, $menu_title, $capability, __FILE__, $function, $icon_url, $position);
