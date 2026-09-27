<?php

declare(strict_types=1);

namespace {
    // lowercase_hook_name_is_valid
    do_action('myplugin_post_saved', $post_id);

    // lowercase_filter_name_is_valid
    $value = apply_filters('myplugin_option_value', $value);

    // digits_and_underscores_are_valid
    do_action('myplugin_v2_loaded');

    // subscribing_functions_are_not_flagged
    add_action('Third-Party.Hook', 'my_callback');
    add_filter('Another/Hook', 'my_callback');
    remove_action('Bad Name', 'my_callback');
    remove_filter('Bad-Name', 'my_callback');

    // dynamic_parts_are_ignored
    do_action("myplugin_{$type}_saved", $post_id);

    // non_literal_hook_name_is_ignored
    do_action($hook_name, $post_id);

    // escape_sequences_in_interpolated_names_are_not_flagged
    do_action("myplugin_\x67ood_{$type}");

    // unicode_escape_in_interpolated_name_is_not_flagged
    do_action("\u{1F600}_hook_{$type}");

    // uppercase_hook_name_is_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    do_action('MyPlugin_Post_Saved', $post_id);

    // hyphen_separator_is_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    $value = apply_filters('myplugin-option-value', $value);

    // space_separator_is_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    do_action('myplugin post saved');

    // period_is_flagged_by_default
    // @mago-expect lint:wordpress/valid-hook-name
    do_action('myplugin.loaded');

    // uppercase_and_hyphen_are_flagged_separately
    // @mago-expect lint:wordpress/valid-hook-name(2)
    do_action('MyPlugin-Loaded');

    // literal_parts_of_interpolated_names_are_validated
    // @mago-expect lint:wordpress/valid-hook-name
    do_action("MyPlugin_{$type}_saved", $post_id);

    // fully_qualified_call_is_checked
    // @mago-expect lint:wordpress/valid-hook-name
    \do_action('MyPlugin_Loaded');

    // hex_escaped_uppercase_is_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    do_action("\x41\x42_hook_{$type}");

    // octal_escaped_uppercase_is_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    do_action("\101\102_hook_{$type}");

    // escaped_backslash_in_interpolated_name_is_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    do_action("myplugin\\action_{$id}");

    // ref_array_dispatchers_are_checked
    // @mago-expect lint:wordpress/valid-hook-name
    do_action_ref_array('MyPlugin_Loaded', [$post]);
    // @mago-expect lint:wordpress/valid-hook-name
    apply_filters_ref_array('myplugin-value', [$value]);

    // bad_delimiter_next_to_escapes_is_still_flagged
    // @mago-expect lint:wordpress/valid-hook-name
    do_action("\u{1F600}-hook_{$type}");

    // binary_prefixed_literal_is_checked
    // @mago-expect lint:wordpress/valid-hook-name
    do_action(b'MyPlugin_Loaded');

    // unknown_escape_keeps_its_backslash
    // @mago-expect lint:wordpress/valid-hook-name
    do_action("myplugin\d_{$type}");

    // heredoc_literal_parts_are_validated
    // @mago-expect lint:wordpress/valid-hook-name
    do_action(<<<HOOK
        MyPlugin_{$type}
        HOOK);

    // lowercase_heredoc_is_valid
    do_action(<<<'HOOK'
        myplugin_loaded
        HOOK);

    // deprecated_dispatchers_are_not_checked
    do_action_deprecated('MyPlugin-Old', [], '1.0');
}
