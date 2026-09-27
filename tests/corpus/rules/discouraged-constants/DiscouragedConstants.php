<?php

declare(strict_types=1);

// bare_constant_usage_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo STYLESHEETPATH;

// fully_qualified_global_constant_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo \STYLESHEETPATH;

// constant_as_function_argument_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
$discouraged_constants_folder = basename(TEMPLATEPATH);

// constant_concatenated_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
include PLUGINDIR . '/js/myfile.js';

// mu_plugin_dir_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo MUPLUGINDIR;

// header_image_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo HEADER_IMAGE;

// no_header_text_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo NO_HEADER_TEXT;

// header_textcolor_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo HEADER_TEXTCOLOR;

// header_image_width_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo HEADER_IMAGE_WIDTH;

// header_image_height_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo HEADER_IMAGE_HEIGHT;

// background_color_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo BACKGROUND_COLOR;

// background_image_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
echo BACKGROUND_IMAGE;

// use_const_bare_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
use const STYLESHEETPATH as SSP;

// switch_subject_and_case_are_both_flagged
switch (
    // @mago-expect lint:wordpress/discouraged-constants
    STYLESHEETPATH
) {
    // @mago-expect lint:wordpress/discouraged-constants
    case STYLESHEETPATH:
        break;
}

// define_declaration_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
define('STYLESHEETPATH', 'something');

// top_level_const_declaration_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
const STYLESHEETPATH = 'something';

// named_argument_define_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
\DEFINE(case_insensitive: false, constant_name: 'STYLESHEETPATH', value: 'something');

// define_with_comments_between_arguments_is_flagged
Define(
    // Name.
    // @mago-expect lint:wordpress/discouraged-constants
    'STYLESHEETPATH',
    // Value.
    'something',
);

// fully_qualified_define_call_is_flagged
// @mago-expect lint:wordpress/discouraged-constants
\define('HEADER_IMAGE', 'something');

// defined_check_is_not_flagged
if (defined('STYLESHEETPATH')) {
    // Do something unrelated.
}

// new_without_parens_is_not_flagged
$discouraged_constants_a = new STYLESHEETPATH();

// instanceof_is_not_flagged
if ($discouraged_constants_abc instanceof STYLESHEETPATH) {
}

// namespace_relative_reference_is_not_flagged
echo My\Plugin\STYLESHEETPATH;

// class_constant_access_is_not_flagged
echo My_Class::STYLESHEETPATH;

// property_access_is_not_flagged
echo $this->STYLESHEETPATH;

// function_call_with_same_name_is_not_flagged
echo STYLESHEETPATH();

// namespaced_define_call_is_not_flagged
MyNamespace\define('PLUGINDIR', 'something');

// namespaced_define_argument_is_not_flagged
define('My\STYLESHEETPATH', 'something');

// use_const_namespaced_is_not_flagged
use const SomeNamespace\STYLESHEETPATH as SSP2;

// use_const_group_is_not_flagged
use const SomeNamespace\{STYLESHEETPATH, TEMPLATEPATH};

// use_const_alias_named_after_constant_is_not_flagged
use const ABC as STYLESHEETPATH;
