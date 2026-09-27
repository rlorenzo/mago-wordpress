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

    // leading_underscore_is_ignored
    function _myplugin_internal() {}

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

namespace App\Plugin {
    // namespaced_code_is_ignored
    function init() {}

    class Admin {
        public function boot() {
            do_action('booted');
        }
    }

    const VERSION = '1.0.0';

    function run() {
        define('PLUGIN_DIR', '/tmp');
        do_action('run');
    }
}
