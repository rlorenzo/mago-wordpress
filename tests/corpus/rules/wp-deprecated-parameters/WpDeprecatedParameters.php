<?php

declare(strict_types=1);

namespace {
    // default_value_used_is_not_flagged (positional, string default)
    wp_title_rss('&#8211;');

    // default_value_used_is_not_flagged (positional, true default)
    wp_get_sidebars_widgets(true);

    // default_value_used_is_not_flagged (null default, not the last argument)
    wp_new_user_notification('', null, '');

    // default_value_used_is_not_flagged (false default, not the last argument)
    the_attachment_link('', false, false, false);

    // default_value_used_is_not_flagged (null default, not the last argument)
    update_blog_option('', '', '', null);

    // default_value_used_is_not_flagged (empty string default, last argument)
    wp_install('', '', '', '', '');

    // default_value_used_is_not_flagged (empty-array default, legacy array syntax)
    get_category_parents('', '', '', '', array());

    // default_value_used_is_not_flagged (empty-array default, short array syntax)
    get_category_parents('', '', '', '', []);

    // dynamic_value_is_still_flagged (variable)
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_new_user_notification('', $variable);

    // dynamic_value_is_still_flagged (function call)
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_new_user_notification('', function_name());

    // dynamic_value_is_still_flagged (method call)
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_new_user_notification('', $this->method_name());

    // named_argument_skipped_parameter_is_not_flagged
    wp_install(blog_title: '', user_name: '', user_email: '', is_public: '');

    // named_argument_only_a_later_optional_parameter_is_passed
    wp_install(blog_title: '', user_name: '', user_email: '', is_public: '', language: '');

    // named_argument_default_value_mixed_positional_and_named_unconventional_order
    wp_install('', '', deprecated: '', user_email: '', is_public: '');

    // named_argument_default_value_all_named_unconventional_order
    wp_install(user_name: '', deprecated: '', user_email: '', blog_title: '', is_public: '');

    // named_argument_non_default_value_unconventional_order
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_install(is_public: '', user_name: '', user_email: '', deprecated: 'should be empty', blog_title: '');

    // fully_qualified_call_is_flagged
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    \add_option('', '', []);

    // namespace_qualified_call_is_not_flagged
    MyNamespace\get_blog_list($foo, $bar, 'deprecated');

    // fully_namespace_qualified_call_is_not_flagged
    \MyNamespace\get_wp_title_rss('deprecated');

    // relative_namespace_call_is_flagged (count = 2)
    // WPCS's own sniff cannot resolve `namespace\...` relative to the file's
    // namespace, so it leaves this unflagged (see its test fixture comment).
    // Mago resolves it correctly: in the global namespace, `namespace\the_author`
    // is `the_author`, so this rule flags it, more precisely than the sniff.
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    namespace\the_author('deprecated', 'deprecated');

    // relative_sub_namespace_call_is_not_flagged
    namespace\Sub\wp_title_rss('deprecated');

    // Every case below passes a non-default value and is flagged, alphabetically.

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    _future_post_hook(10, $post);

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    _load_remote_block_patterns($value);

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    _wp_post_revision_fields($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    add_option('', '', []);

    // case_insensitive_match (fully qualified)
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    \Add_Option('', '', 1.23);

    // case_insensitive_match
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    ADD_OPTION('', '', 10);

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    add_option('', '', false);

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    add_option('', '', 'deprecated');

    // multiple_deprecated_parameters_on_one_call (count = 2)
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    comments_link('deprecated', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    convert_chars('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    delete_plugins($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    discover_pingback_server_uri('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_blog_list($foo, $bar, 'deprecated');

    // non_empty_array_does_not_match_the_empty_array_default
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_category_parents('', '', '', '', array('deprecated'));

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_delete_post_link('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_last_updated('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_site_option($foo, $bar, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_terms($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_the_author('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_user_option('', '', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    get_wp_title_rss('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    iframe_header($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    install_search_form('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    is_email('', 'deprecated');

    // string_false_is_not_the_boolean_false_default
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    is_email('', 'false');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    load_plugin_textdomain('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    newblog_notify_siteadmin($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    permalink_single_rss('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    redirect_this_site('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    register_meta('', '', '', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    safecss_filter_attr('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    switch_to_blog($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    term_description($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    the_attachment_link('', '', 'deprecated');

    // multiple_deprecated_parameters_on_one_call (count = 2)
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    the_author('deprecated', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    the_author_posts_link('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    trackback_rdf('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    trackback_url('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    unregister_setting('', '', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    update_blog_option('', '', '', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    update_blog_status('', '', '', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    update_posts_count('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    update_user_status('', '', '', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_count_terms($foo, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_create_thumbnail($foo, $bar, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_get_http_headers('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_get_sidebars_widgets('deprecated');

    // positional_argument_not_the_last_one_passed
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_install('', '', '', '', 'deprecated', 'password', 'language');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_login($foo, $bar, 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_new_user_notification('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_notify_postauthor('', 'deprecated');

    // string_null_is_not_the_null_default
    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_notify_postauthor('', 'null');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_title_rss('deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    wp_upload_bits('', 'deprecated');

    // @mago-expect lint:wordpress/wp-deprecated-parameters
    xfn_check('', '', 'deprecated');

    // deprecated_after_default_minimum_wp_version_is_not_flagged
    // global_terms()'s $deprecated parameter was deprecated in WordPress 6.1,
    // inject_ignored_hooked_blocks_metadata_attributes()'s in 6.5.3, and
    // wp_render_elements_support_styles()'s in 6.6 and _wp_can_use_pcre_u()'s
    // $set in 6.9 -- all after the default minimum-wp-version (6.0), so none
    // of these four are flagged here. Covered instead by
    // WpDeprecatedParameterRulesTest with a higher minimum.
    global_terms($foo, 'deprecated');
    inject_ignored_hooked_blocks_metadata_attributes('', 'deprecated');
    wp_render_elements_support_styles('deprecated');
    _wp_can_use_pcre_u('deprecated');
}
