<?php

declare(strict_types=1);

namespace {
    // deprecated_function_with_replacement
    // @mago-expect lint:wordpress/wp-deprecated-functions
    get_settings('siteurl');

    // deprecated_function_without_replacement
    // @mago-expect lint:wordpress/wp-deprecated-functions
    screen_icon();

    // multiple_deprecated_functions (count = 2)
    // @mago-expect lint:wordpress/wp-deprecated-functions
    echo wp_specialchars($text);
    // @mago-expect lint:wordpress/wp-deprecated-functions
    $url = clean_url('https://example.com');

    // case_insensitive_match
    // @mago-expect lint:wordpress/wp-deprecated-functions
    $user = Get_CurrentUserInfo();

    // fully_qualified_call_is_flagged
    // @mago-expect lint:wordpress/wp-deprecated-functions
    $value = \get_settings('siteurl');

    // non_deprecated_functions_pass
    $value = get_option('siteurl');
    $user = wp_get_current_user();
    echo esc_html($value);

    // method_call_with_same_name_is_not_flagged
    $legacy->get_settings('siteurl');
    Legacy::get_settings('siteurl');

    // namespaced_function_call_is_not_flagged
    \MyPlugin\Compat\get_settings('siteurl');

    // deprecated_before_default_minimum_wp_version_is_flagged
    // get_page_by_title() has been deprecated since WordPress 6.2; the default
    // minimum-wp-version (6.7, as in WPCS 3.4.1) has reached it.
    // @mago-expect lint:wordpress/wp-deprecated-functions
    $page = get_page_by_title('About');

    // deprecated_after_default_minimum_wp_version_is_still_flagged
    // seems_utf8() has been deprecated since WordPress 6.9, after the default
    // minimum-wp-version (6.7): reported with a note, as WPCS reports it as a warning.
    // @mago-expect lint:wordpress/wp-deprecated-functions
    $utf8 = seems_utf8($value);

    // aliased_use_function_import_is_flagged
    // @mago-expect lint:wordpress/wp-deprecated-functions
    use function popuplinks as something_else;
}

// imported_namespaced_function_is_not_flagged
namespace App {
    use function MyPlugin\Compat\get_settings;

    $value = get_settings('siteurl');
}
