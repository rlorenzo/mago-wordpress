<?php

// Global variable writes (WPCS process_variable_assignment), ported from PrefixAllGlobalsUnitTest.*.inc.

// imported_globals_are_checked
function myplugin_do_something_with_globals() {
    global $something, $else;

    // @mago-expect lint:wordpress/prefix-all-globals
    $something = 'value';
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS['something'] = 'value';
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS['something' . $else] = 'value';
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS["something_{$else}"] = 'value';
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS["something$else"] = 'value';
}

// top_level_assignment_is_checked
// @mago-expect lint:wordpress/prefix-all-globals
$var = 'abc';

// prefixed_writes_are_ok
function myplugin_do_something() {
    global $myplugin_something, $else;

    $myplugin_something = 'value';
    $GLOBALS['myplugin_something'] = 'value';
    $GLOBALS['myplugin_' . $else] = 'value';
    $GLOBALS["myplugin_something_{$else}"] = 'value';
}

$myplugin_var = 'abc';

// name_equal_to_prefix_is_ok
function myplugin() {
    global $myplugin;

    $myplugin = 'value';
    $GLOBALS['myplugin'] = 'value';
    $GLOBALS['myplugin' . $else] = 'value';
    $GLOBALS["myplugin{$else}"] = 'value';
    $GLOBALS["myplugin$else"] = 'value';
}

$myplugin = 'abc';

// function_locals_params_and_properties_are_ok
function myplugin_do_something_else($param = 'default') {
    $var = 'abc';
    ${$something} = 'value';
}

function ($param) {
    $var = 'abc';
};

class MyPlugin_Example {
    public $var = 'abc';

    public function do_something($param = 'default') {}

    public function __construct(
        public int $timestart = 0,
        protected int|bool $timeend = false,
        $post = null,
    ) {}
}

$myplugin_class = new class {
    public $var = 'abc';
};

// superglobals_and_wp_globals_are_ok
$_POST['something'] = 'value';
$_SESSION['something'] = 'value';
$GLOBALS = 'overwritten';

function myplugin_do_another_thing() {
    global $post;
    $post = 'value';
    $GLOBALS['post'] = 'value';
}

function myplugin_content_width() {
    $GLOBALS['content_width'] = apply_filters('myplugin_content_width', 640);
}

// old_style_ignore_comment_is_not_supported
// @mago-expect lint:wordpress/prefix-all-globals
$something = 'abc'; // WPCS: prefix ok.

// dynamic_names_are_flagged (warnings in WPCS)
function myplugin_something() {
    global $something;

    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS[$something] = 'value';
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS["{$something}_something"] = 'value';
}

// @mago-expect lint:wordpress/prefix-all-globals
$$something = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
${$something} = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
$$$${$something} = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
${$something}['foo'] = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
${$something}['foo']['bar'] = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
${$something['foo']} = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
$GLOBALS[$something] = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
$GLOBALS["{$something}_something"] = 'value';
// @mago-expect lint:wordpress/prefix-all-globals
$GLOBALS[${$something}] = 'value';

class MyPlugin_Dynamic {
    public function run() {
        global $myplugin_filter_var;
        // @mago-expect lint:wordpress/prefix-all-globals
        ${$this->name} = 'value';
    }
}

// reading_a_variable_variable_is_ok
echo ${$testing_non_assignment_variable_variable}['deref'];

// control_structure_conditions_are_checked
if (($myplugin_abc = function_call()) === true) {}
// @mago-expect lint:wordpress/prefix-all-globals
if (($abc = function_call()) === true) {}

$myplugin_something = [];
foreach ($myplugin_something as $myplugin_some) {}
// @mago-expect lint:wordpress/prefix-all-globals
foreach ($myplugin_something as $something) {}
// @mago-expect lint:wordpress/prefix-all-globals
foreach ($myplugin_something as $key => $myplugin_something) {}
// @mago-expect lint:wordpress/prefix-all-globals
foreach ($myplugin_something as $myplugin_key => $something) {}
// @mago-expect lint:wordpress/prefix-all-globals(2)
foreach ($myplugin_something as $key => $something) {}
// @mago-expect lint:wordpress/prefix-all-globals
foreach ($myplugin_something as &$something) {}

while (($myPluginSomething = function_call()) === true) {}
// @mago-expect lint:wordpress/prefix-all-globals
while (($something = function_call()) === true) {}

for ($myplugin_i = 0; $myplugin_i < 10; $myplugin_i++) {}
// @mago-expect lint:wordpress/prefix-all-globals
for ($i = 0; $i < 10; $i++) {}

switch (true) {
    case ($myplugin_case = 'abc'):
        break;
    // @mago-expect lint:wordpress/prefix-all-globals
    case ($case = 'abc'):
        break;
    case ($case === 'abc'):
        break;
}

// compound_and_array_element_writes_are_checked
// @mago-expect lint:wordpress/prefix-all-globals
$counter .= 'x';
// @mago-expect lint:wordpress/prefix-all-globals
$items[] = 'x';
// @mago-expect lint:wordpress/prefix-all-globals
$items['key']['sub'] = 'x';
// @mago-expect lint:wordpress/prefix-all-globals
$GLOBALS['my_key']['sub'] = 'x';
$myplugin_object?->property = 10;
$myplugin_object->property = 10;

// non_global_scope_is_ok
function myPluginFunction() {
    if (($abc = function_call()) === true) {}
    foreach ($myplugin_something as $something) {}
    foreach ($myplugin_something as $key => $something) {}
    while (($something = function_call()) === true) {}
    for ($i = 0; $i < 10; $i++) {}

    switch (true) {
        case ($case = 'abc'):
            break;
    }
}

// list_assignments_are_checked
list() = $array;
list(, ,) = $array;

// @mago-expect lint:wordpress/prefix-all-globals(2)
list($var1, , $var2) = $array;
list($myplugin_var1, $myplugin_var2) = $array;

// @mago-expect lint:wordpress/prefix-all-globals(2)
[$var1, $var2] = $array;
[$myplugin_var1, $myplugin_var2] = $array;

// @mago-expect lint:wordpress/prefix-all-globals(2)
list((string) $a => $store['B'], (string) $c => $store['D']) = $e->getIndexable();
// @mago-expect lint:wordpress/prefix-all-globals
[$foo => $GLOBALS['bar']] = $bar;

// @mago-expect lint:wordpress/prefix-all-globals(4)
list($var1, , list($var2, $var3), $var4) = $array;

// @mago-expect lint:wordpress/prefix-all-globals(2)
list($foo['key'], $foo[$bar]) = $array;

// @mago-expect lint:wordpress/prefix-all-globals(2)
foreach ($array as [$var1, $var2]) {}

function myplugin_lists_in_function_scope() {
    global $store, $c;

    list($var1, , $var2) = $array;
    [$var1, $var2] = $array;

    // @mago-expect lint:wordpress/prefix-all-globals(2)
    list((string) $a => $store['B'], (string) $c => $store['D']) = $e->getIndexable();
    // @mago-expect lint:wordpress/prefix-all-globals
    [$foo => $GLOBALS['bar']] = $bar;

    // @mago-expect lint:wordpress/prefix-all-globals
    list($var1, , list($c, $var3), $var4) = $array;

    list($foo['key'], $foo[$c]) = $array;
}

// arrow_functions_are_skipped_but_closures_inside_them_are_not
$myplugin_fn = fn($foo = 10, $bar = 20) => $foo;
$myplugin_fn = fn($myplugin_name, $myplugin_value) => $$myplugin_name = $myplugin_value;
$myplugin_fn = fn($myplugin_name, $myplugin_value) => $new = $myplugin_value;
$myplugin_fn = fn($a, $b) => $no_prefix = function ($a, $b) {
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS['my_key'] = 10;

    return $a + $b;
};

function myplugin_null_coalesce_equals() {
    // @mago-expect lint:wordpress/prefix-all-globals
    $GLOBALS['my_key'] ??= 10;
}

// global_statement_must_precede_the_write_in_the_same_scope
function myplugin_close_tag_can_end_global_statement() {
    global $something, $myplugin_else ?>
    <?php
    echo $breakOutOfTheStatement;
    $myplugin_else = 'value';
    // @mago-expect lint:wordpress/prefix-all-globals
    $something = 'value';
    $breakOutOfTheStatement = 'value';
}

function myplugin_only_check_global_statement_in_current_scope() {
    $closure = function () {
        global $something;
        return $something;
    };

    $something = 'value';
}

function myplugin_write_before_import() {
    $something = 'value';
    global $something;
}

// test_classes_are_skipped
class Globals_Test extends WP_UnitTestCase {
    public function test_globals() {
        global $something;
        $something = 'value';
        $GLOBALS['something'] = 'value';
    }
}
