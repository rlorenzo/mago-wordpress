<?php

declare(strict_types=1);

namespace {
    // top_level_override_is_flagged
    // @mago-expect lint:wordpress/global-variables-override
    $post = get_post(123);

    // top_level_compound_assignment_is_flagged
    // @mago-expect lint:wordpress/global-variables-override
    $wp_version .= '-modified';

    // globals_array_write_with_dollar_key_is_flagged
    // @mago-expect lint:wordpress/global-variables-override
    $GLOBALS['$wp_query'] = new \WP_Query();

    // nested_top_level_assignment_is_flagged
    if (is_admin()) {
        // @mago-expect lint:wordpress/global-variables-override
        $pagenow = 'index.php';
    }

    // globals_write_with_other_key_is_not_flagged
    $GLOBALS['my_plugin_state'] = [];

    // globals_write_with_dynamic_key_is_not_flagged
    $GLOBALS[$key] = 'value';

    // other_variable_names_are_not_flagged
    $my_post = get_post(123);

    // list_destructuring_is_not_flagged
    [$post_pair, $page_pair] = my_plugin_get_pair();

    // property_write_is_not_flagged
    global $post;
    $post->ID = 5;

    // closure_without_global_import_is_not_flagged
    $callback_without_import = function () {
        $post = get_post(123);
    };

    // arrow_function_variable_assignment_is_not_flagged
    $arrow_variable_assignment = fn() => $post = get_post(123);

    // globals_write_in_arrow_function_is_flagged
    $arrow_globals_write = fn() =>
        // @mago-expect lint:wordpress/global-variables-override
        $GLOBALS['post'] = get_post(123);

    // override_after_global_import_is_flagged
    function my_plugin_setup_with_import()
    {
        global $post;
        // @mago-expect lint:wordpress/global-variables-override
        $post = get_post(123);
    }

    // globals_array_write_is_flagged
    function my_plugin_setup_globals_write()
    {
        // @mago-expect lint:wordpress/global-variables-override
        $GLOBALS['post'] = get_post(123);
    }

    // global_import_in_nested_block_is_flagged
    function my_plugin_setup_nested_import()
    {
        if (is_admin()) {
            global $wp_query;
        }
        // @mago-expect lint:wordpress/global-variables-override
        $wp_query = new \WP_Query();
    }

    // local_variable_in_function_is_not_flagged
    function my_plugin_render()
    {
        $post = get_post(123); // Local variable, not the global.
    }

    // reading_a_global_is_not_flagged
    function my_plugin_title()
    {
        global $post;
        return $post->post_title;
    }

    // array_element_write_is_not_flagged
    function my_plugin_array_element()
    {
        global $wp_filter;
        $wp_filter['init'] = 'something';
    }

    // assignment_before_global_import_is_not_flagged
    function my_plugin_pre_import()
    {
        $post = get_post(123); // Still local at this point.
        global $post;
        return $post;
    }

    // outer_global_import_does_not_leak_into_closure
    function my_plugin_outer_import()
    {
        global $post;
        $callback = function () {
            $post = get_post(123);
        };
    }

    // override_in_method_with_global_import_is_flagged
    class MyPluginGlobals
    {
        public function setup()
        {
            global $current_user;
            // @mago-expect lint:wordpress/global-variables-override
            $current_user = wp_get_current_user();
        }
    }
}

namespace MyPlugin {
    // namespaced_top_level_is_still_flagged
    // @mago-expect lint:wordpress/global-variables-override
    $wp_query = new \WP_Query();
}
