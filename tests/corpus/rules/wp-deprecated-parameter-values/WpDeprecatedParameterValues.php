<?php

declare(strict_types=1);

namespace {
    // deprecated_show_value
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    get_bloginfo('home');

    // case_insensitive_match_and_fully_qualified_call
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    \GeT_bLoGiNfO('siteurl');

    // double_quoted_string_is_still_matched
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    Get_Bloginfo("text_direction");

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    echo bloginfo('home');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    echo bloginfo("siteurl");

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    echo bloginfo('text_direction');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_settings_field('', '', '', 'misc');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_settings_field('', '', '', 'privacy');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_settings_section('', '', '', 'misc');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_settings_section('', '', 'privacy', 'privacy');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    register_setting('misc');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    register_setting('privacy');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    unregister_setting('misc');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    unregister_setting('privacy');

    // optional_parameter_not_passed_is_not_flagged
    add_settings_field('', '', '');

    // Dynamic values are not determined statically, so none of these give a report.

    // dynamic_value_variable_is_not_flagged
    $value = 'text_direction';
    get_bloginfo($value);

    // dynamic_value_constant_is_not_flagged
    get_bloginfo(CONSTANT);

    // dynamic_value_function_call_is_not_flagged
    echo bloginfo(function_name());

    // non_deprecated_value_is_not_flagged
    get_bloginfo('wpurl');

    // trailing_comma_with_no_argument_is_not_flagged
    add_settings_field('', '', '', /* deliberately not passed */);

    // Safeguard support for PHP 8.0+ named parameters.

    // named_argument_optional_parameter_skipped_is_not_flagged
    add_settings_section($id, $title, $callback, args: $args);

    // named_argument_dynamic_value_is_undetermined
    add_settings_field(page: $page, section: $section, id: $id, title: $title, callback: $callback);

    // named_argument_not_the_deprecated_value_is_not_flagged
    add_settings_section(callback: $callback, page: 'general', section: $section, id: $id, title: $title);

    // named_argument_deprecated_value_unconventional_order
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_settings_field(page: 'misc', section: $section, id: $id, title: $title, callback: $callback);

    // named_argument_optional_parameter_skipped_is_not_flagged
    get_bloginfo(filter: $filter);

    // named_argument_dynamic_value_is_undetermined
    get_bloginfo(filter: $filter, show: $show);

    // named_argument_not_the_deprecated_value_is_not_flagged
    get_bloginfo(filter: $filter, show: 'admin_email');

    // named_argument_deprecated_value
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    get_bloginfo(filter: $filter, show: 'text_direction');

    // Parameter values deprecated in WordPress 5.5, at or before the default minimum-wp-version (6.0).

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_option('blacklist_keys');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_option('comment_whitelist', $value);

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    add_option(value: $value, option: 'blacklist_keys');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    get_option('blacklist_keys');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    get_option('comment_whitelist', $default);

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    get_option(default: $default, option: 'comment_whitelist');

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    update_option('blacklist_keys', $value);

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    update_option('comment_whitelist', $value);

    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    update_option(autoload: true, value: $value, option: 'blacklist_keys');

    // wp_get_typography_font_size_value()'s boolean $settings values were deprecated in WordPress
    // 6.6, after the default minimum-wp-version (6.0), so they are not flagged here. Covered
    // instead by WpDeprecatedRulesTest with a higher minimum. An empty-array $settings is not a
    // deprecated value at all.
    wp_get_typography_font_size_value($preset, array());

    // Safeguard correct handling of all types of namespaced function calls.

    // fully_qualified_call_is_flagged
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    \get_bloginfo('home');

    // namespace_qualified_call_is_not_flagged
    MyNamespace\register_setting('privacy');

    // fully_namespace_qualified_call_is_not_flagged
    \MyNamespace\unregister_setting('misc');

    // relative_namespace_call_is_flagged
    // WPCS's own sniff cannot resolve `namespace\...` relative to the file's
    // namespace, so it leaves this unflagged (see its test fixture comment).
    // Mago resolves it correctly: in the global namespace, `namespace\get_option`
    // is `get_option`, so this rule flags it, more precisely than the sniff.
    // @mago-expect lint:wordpress/wp-deprecated-parameter-values
    namespace\get_option('blacklist_keys');

    // relative_sub_namespace_call_is_not_flagged
    namespace\Sub\add_option('comment_whitelist', $value);
}
