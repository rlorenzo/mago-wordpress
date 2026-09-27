<?php

declare(strict_types=1);

// Ports WordPress.WP.ClassNameCaseUnitTest.inc, restricted to the sniff's
// $wp_classes group (see CoreClasses); the bundled-library groups are out
// of this rule's scope.

// self_and_static_are_disregarded
class UsesHierarchyKeywords
{
    public function bar()
    {
        $obj = new self();
        echo static::MY_CONSTANT;
    }
}

// not_a_wp_core_class_is_disregarded
$obj = new Not_A_WP_Core_Class();

// correct_case_instantiation
$obj = new WP_Importer();

// correct_case_fully_qualified_instantiation
$obj = new \WP_Query();

// correct_case_extends
class MyList extends WP_List_Table
{
}

// correct_case_static_property_access
echo WP_User_Search::$users_per_page;

// correct_case_static_call
WP_Customize_New_Menu_Control::foo();

// wrong_case_instantiation_wpdb
// @mago-expect lint:wordpress/class-name-case
$obj = new WPDB();

// wrong_case_fully_qualified_instantiation
// @mago-expect lint:wordpress/class-name-case
$obj = new \WP_date_query();

// wrong_case_extends
// @mago-expect lint:wordpress/class-name-case
class MyListWrong extends \WP_LIST_table
{
}

// wrong_case_static_property_access
// @mago-expect lint:wordpress/class-name-case
echo wp_user_search::$users_per_page;

// wrong_case_static_call
// @mago-expect lint:wordpress/class-name-case
WP_Customize_NEW_Menu_Control::foo();

// wrong_case_relative_namespace_instantiation
// @mago-expect lint:wordpress/class-name-case
$obj = new namespace\pop3();

// wrong_case_ai_client_class
// @mago-expect lint:wordpress/class-name-case
$obj = new \WP_ai_client_cache();

// implements_wrong_case
// @mago-expect lint:wordpress/class-name-case
class ImplementsWrongCase implements walker_nav_menu
{
}

// class_in_another_namespace_is_out_of_scope
$obj = new MyNamespace\WP_Query();
