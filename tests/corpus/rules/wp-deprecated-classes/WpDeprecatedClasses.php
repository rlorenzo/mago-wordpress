<?php

declare(strict_types=1);

namespace {
    // instantiation_of_deprecated_class
    // @mago-expect lint:wordpress/wp-deprecated-classes
    $search = new WP_User_Search($_GET['usersearch']);

    // case_insensitive_class_name
    // @mago-expect lint:wordpress/wp-deprecated-classes
    $search = new wp_user_search();

    // leading_backslash_class_name
    // @mago-expect lint:wordpress/wp-deprecated-classes
    $json = new \Services_JSON();

    // static_method_call_on_deprecated_class
    // @mago-expect lint:wordpress/wp-deprecated-classes
    WP_HTTP_Fsockopen::test();

    // class_constant_access_on_deprecated_class
    // @mago-expect lint:wordpress/wp-deprecated-classes
    $version = WP_Privacy_Data_Export_Requests_Table::VERSION;

    // extends_deprecated_class
    // @mago-expect lint:wordpress/wp-deprecated-classes
    class My_Settings_Section extends WP_Customize_New_Menu_Control {}

    // instanceof_deprecated_class
    // @mago-expect lint:wordpress/wp-deprecated-classes
    if ($item instanceof WP_Privacy_Data_Removal_Requests_Table) {
        return true;
    }

    // non_deprecated_class_is_not_flagged
    $query = new WP_User_Query(['role' => 'editor']);

    // qualified_class_name_is_not_flagged
    $search = new WP\Compat\WP_User_Search();

    // method_and_property_usage_is_not_flagged
    $object->WP_User_Search();
    $object->WP_User_Search;

    // deprecated_before_default_minimum_wp_version_is_flagged
    // WP_Http_Curl has been deprecated since WordPress 6.4; the default
    // minimum-wp-version (6.7, as in WPCS 3.4.1) has reached it.
    // @mago-expect lint:wordpress/wp-deprecated-classes
    $transport = new WP_Http_Curl();
}

// same_named_class_in_namespace_is_not_flagged
namespace My\Plugin {
    $search = new WP_User_Search();
    WP_User_Search::run();
    $version = WP_User_Search::VERSION;

    if ($search instanceof WP_User_Search) {
        return;
    }
}

// imported_namespaced_class_is_not_flagged
namespace App\Importer {
    use MyPlugin\Compat\WP_User_Search;

    $search = new WP_User_Search();
}
