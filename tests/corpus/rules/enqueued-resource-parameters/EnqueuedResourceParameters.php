<?php

declare(strict_types=1);

namespace {
    // script_with_version_and_in_footer
    wp_enqueue_script('my-script', 'https://example.com/js/app.js', [], '1.2.3', true);
    wp_register_script('other', 'https://example.com/js/other.js', [], '2.0.0', ['in_footer' => true]);

    // style_with_version
    wp_enqueue_style('my-style', 'https://example.com/css/app.css', [], '1.2.3');
    wp_register_style('other', 'https://example.com/css/other.css', [], '1.0', 'print');

    // enqueue_by_handle_only
    wp_enqueue_script('jquery');
    wp_enqueue_style('common');

    // alias_handles_without_src_are_fine
    wp_register_script('my-bundle', false, ['a', 'b']);
    wp_register_style('my-inline', null);
    wp_register_script('my-inline', '');
    wp_register_style('my-inline-2', (''));

    // dynamic_version_values_are_fine
    wp_enqueue_script('a', $src, [], $version, true);
    wp_enqueue_script('b', $src, [], MY_PLUGIN_VERSION, true);
    wp_enqueue_script('c', $src, [], filemtime($path), true);
    wp_enqueue_style('d', $src, [], '20240101');
    wp_enqueue_style('e', $src, [], 1.5);

    // spread_arguments_bail_out
    wp_enqueue_script(...$args);
    wp_enqueue_script('my-script', ...$rest);

    // unknown_named_argument_bails_out
    wp_enqueue_script('my-script', src: $src, unknown: true);

    // named_arguments_with_version_and_footer
    wp_enqueue_script('my-script', $src, ver: '1.2.3', in_footer: true);
    wp_enqueue_style('my-style', $src, ver: '1.2.3');

    // mixed_case_named_arguments_are_recognized
    wp_enqueue_script('my-script', $src, Ver: '1.2.3', In_Footer: true);
    wp_enqueue_style('my-style', $src, VER: '1.2.3');

    // unrelated_function_is_ignored
    my_enqueue_script('my-script', $src);
    \My\wp_enqueue_script('my-script', $src);

    // script_missing_version_and_footer
    // @mago-expect lint:wordpress/enqueued-resource-parameters(2)
    wp_enqueue_script('my-script', 'https://example.com/js/app.js', []);

    // script_with_false_version
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_register_script('my-script', 'https://example.com/js/app.js', [], false, true);

    // script_with_null_version
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_enqueue_script('my-script', 'https://example.com/js/app.js', [], null, true);

    // style_missing_version
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_enqueue_style('my-style', 'https://example.com/css/app.css');

    // style_with_false_version
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_register_style('my-style', 'https://example.com/css/app.css', [], false);

    // script_missing_in_footer_only
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_enqueue_script('my-script', 'https://example.com/js/app.js', [], '1.2.3');

    // fully_qualified_call_is_checked
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    \wp_enqueue_style('my-style', 'https://example.com/css/app.css');

    // uppercase_call_is_checked
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    WP_Enqueue_Style('my-style', 'https://example.com/css/app.css');

    // named_ver_false_is_flagged
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_enqueue_style('my-style', $src, ver: false);

    // uppercase_named_ver_false_is_flagged
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_enqueue_style('my-style', $src, VER: false);

    // parenthesized_false_version_is_flagged
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    wp_enqueue_style('my-style', $src, [], (false));
}

namespace App {
    // fully_qualified_call_in_namespace_is_checked
    // @mago-expect lint:wordpress/enqueued-resource-parameters
    \wp_enqueue_style('my-style', 'https://example.com/css/app.css');
}
