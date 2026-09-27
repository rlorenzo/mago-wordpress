<?php

declare(strict_types=1);

namespace {
    // unprefixed_function
    // @mago-expect lint:wordpress/prefix-all-globals
    function init_plugin() {}

    // prefixed_function
    function myplugin_init() {}

    // unprefixed_class
    // @mago-expect lint:wordpress/prefix-all-globals
    class Admin {}

    // prefixed_class_case_insensitive, methods_and_closures_are_not_flagged
    class MyPlugin_Admin {
        public function render() {}
        public function __construct() {}
    }

    // unprefixed_interface_trait_enum
    // @mago-expect lint:wordpress/prefix-all-globals
    interface Renderer {}
    // @mago-expect lint:wordpress/prefix-all-globals
    trait Loggable {}
    // @mago-expect lint:wordpress/prefix-all-globals
    enum Status {}

    // prefixed_interface_trait_enum
    interface MyPlugin_Renderer {}
    trait MyPlugin_Loggable {}
    enum MyPlugin_Status {}

    // unprefixed_const_statement
    // @mago-expect lint:wordpress/prefix-all-globals
    const VERSION = '1.0.0';

    // prefixed_const_statement
    const MYPLUGIN_VERSION = '1.0.0';

    // unprefixed_define
    // @mago-expect lint:wordpress/prefix-all-globals
    define('PLUGIN_DIR', '/tmp');

    // prefixed_define
    define('MYPLUGIN_DIR', '/tmp');

    // namespaced_define_is_ignored
    define('Vendor\PLUGIN_DIR', '/tmp');

    // fully_qualified_calls_are_checked
    // @mago-expect lint:wordpress/prefix-all-globals
    \define('PLUGIN_ROOT', '/tmp');
    // @mago-expect lint:wordpress/prefix-all-globals
    \do_action('booted');

    // leading_backslash_define_is_global
    // @mago-expect lint:wordpress/prefix-all-globals
    define('\PLUGIN_PATH', '/tmp');

    // ref_array_hooks_are_checked
    // @mago-expect lint:wordpress/prefix-all-globals
    do_action_ref_array('loaded', [$post]);
    // @mago-expect lint:wordpress/prefix-all-globals
    apply_filters_ref_array('content', [$content]);

    // unprefixed_hooks
    // @mago-expect lint:wordpress/prefix-all-globals
    do_action('loaded');
    // @mago-expect lint:wordpress/prefix-all-globals
    apply_filters('content', $content);

    // prefixed_hooks_with_separators
    do_action('myplugin_loaded');
    apply_filters('myplugin/content', $content);
    do_action('myplugin-init');

    // leading_underscore_is_not_part_of_the_prefix
    // @mago-expect lint:wordpress/prefix-all-globals
    function _myplugin_internal() {}

    // double_underscore_names_are_not_exempt
    // @mago-expect lint:wordpress/prefix-all-globals
    function __arbitrary() {}
    // @mago-expect lint:wordpress/prefix-all-globals
    define('__PLUGIN_DIR__', '/tmp');

    // allowed_core_hooks
    apply_filters('widget_title', $title);
    do_action('add_meta_boxes');

    // overridable_core_constants
    define('WP_DEBUG', true);
    define('\SCRIPT_DEBUG', true);
    const WP_POST_REVISIONS = 5;

    // non_overridable_core_constant_is_flagged
    // @mago-expect lint:wordpress/prefix-all-globals
    define('ABSPATH', '/var/www/');

    // pluggable_functions_and_classes
    function wp_mail() {}
    function WP_Hash_Password() {}
    class WP_User_Search {}

    // php_builtin_backfills
    function array_is_list() {}
    interface Stringable {}
    define('JSON_THROW_ON_ERROR', 4194304);
    const E_USER_DEPRECATED = 16384;

    // deprecated_hook_invocations_are_not_checked
    do_action_deprecated('loaded', [], '1.0.0');
    apply_filters_deprecated('content', [$content], '1.0.0');

    // methods_and_closures_are_not_flagged
    $callback = function () {};
    $mapper = fn($x) => $x;

    // dynamic_names_are_ignored
    define($name, '/tmp');
    do_action($hook);
    apply_filters('myplugin_' . $key, $value);

    // subscribing_to_existing_hooks_is_ok
    add_action('init', 'myplugin_init');
    add_filter('the_content', 'myplugin_filter_content');
    remove_action('wp_head', 'wp_generator');
    remove_filter('the_content', 'wpautop');

    // symbol_equal_to_prefix_is_ok
    function myplugin() {}
    class MyPlugin {}

    // prefix_in_middle_is_flagged
    // @mago-expect lint:wordpress/prefix-all-globals
    function init_myplugin() {}

    // attribute_name_is_not_the_declared_name
    #[MyPlugin_Marker]
    // @mago-expect lint:wordpress/prefix-all-globals
    function boot() {}
    #[Marker]
    class MyPlugin_Marked {}

    // named_arguments_are_checked
    // @mago-expect lint:wordpress/prefix-all-globals
    define(constant_name: 'PLUGIN_URL', value: '/');
    // @mago-expect lint:wordpress/prefix-all-globals
    do_action(hook_name: 'saved');

    // nested_calls_in_global_code_are_checked
    function myplugin_run() {
        // @mago-expect lint:wordpress/prefix-all-globals
        do_action('run');
    }
}

// unprefixed_namespace_name
// @mago-expect lint:wordpress/prefix-all-globals
namespace App\Plugin {
    // namespaced_declarations_are_ignored
    function init() {}

    class Admin {
        public function boot() {
            // namespaced_hooks_are_still_global
            // @mago-expect lint:wordpress/prefix-all-globals
            do_action('booted');
        }
    }

    const VERSION = '1.0.0';

    function run() {
        // namespaced_define_is_still_global
        // @mago-expect lint:wordpress/prefix-all-globals
        define('PLUGIN_DIR', '/tmp');
        // @mago-expect lint:wordpress/prefix-all-globals
        apply_filters_ref_array('run', [$args]);
        do_action('myplugin_run');
        define('MYPLUGIN_RUN', true);
        do_action('widget_title');
        define('WP_DEBUG', true);
    }
}

// prefixed_namespace_names
namespace MyPlugin\Admin {
    function init() {}
}

namespace Example {
    class Admin {}
}
