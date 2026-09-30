<?php

declare(strict_types=1);

// Ports WordPress.WP.ClassNameCaseUnitTest.inc.

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
class MyList extends WP_List_Table {}

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
class MyListWrong extends \WP_LIST_table {}

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
class ImplementsWrongCase implements walker_nav_menu {}

// class_in_another_namespace_is_out_of_scope
$obj = new MyNamespace\WP_Query();

// wrong_case_bundled_library_instantiation
// @mago-expect lint:wordpress/class-name-case
$obj = new GetID3();

// wrong_case_bundled_library_qualified_extends
// @mago-expect lint:wordpress/class-name-case
class MyMailer extends PhpMailer\PhpMailer\PhpMailer {}

// wrong_case_bundled_library_underscored_class
// @mago-expect lint:wordpress/class-name-case
$obj = new Requests_cookie_jar();

// wrong_case_anonymous_class_extends
// @mago-expect lint:wordpress/class-name-case
$anon = new class extends SimplePie_Iri {};

// correct_case_bundled_library_implements
class ImplementsInterfaceCorrectCase implements SimplePie_Cache_Base {}

// wrong_case_bundled_library_implements
// @mago-expect lint:wordpress/class-name-case
class ImplementsInterfaceIncorrectCase implements simplepie_cache_base {}

// wrong_case_relative_namespace_theme_class
// @mago-expect lint:wordpress/class-name-case
class MyClass4 extends namespace\twentynineteen_SVG_icons {}

// wrong_case_fully_qualified_namespaced_interface
// @mago-expect lint:wordpress/class-name-case
class MyClass5 implements \WPORG\REQUESTS\AUTH {}

// bundled_class_under_another_namespace_is_out_of_scope
class MyClass6 implements \MyNamespace\SIMPLEPIE\Cache\namefilter {}

class MyClass7 implements MyNamespace\requests_auth {}

class MyClass8 implements namespace\Sub\WpOrg\REQUESTS\proxy {}

// wrong_case_relative_namespace_interface
// @mago-expect lint:wordpress/class-name-case
class MyClass9 implements namespace\simplepie\CACHE\base {}

// wrong_case_fully_qualified_static_call
// @mago-expect lint:wordpress/class-name-case
\avifinfo\Box::prepare_query();

// bundled_static_call_under_another_namespace_is_out_of_scope
MyNamespace\Avifinfo\CHAN_PROP::prepare_query();
\MyNamespace\Avifinfo\features::prepare_query();
namespace\Sub\AVIFINFO\parser::prepare_query();

// wrong_case_relative_namespace_static_call
// @mago-expect lint:wordpress/class-name-case
namespace\AVIFINFO\TILE::prepare_query();

// correct_case_namespaced_ai_client_class
$obj = new \WordPress\AiClient\AiClient();

// wrong_case_namespaced_ai_client_class
// @mago-expect lint:wordpress/class-name-case
$obj = new WordPress\AiClient\aiclient();
