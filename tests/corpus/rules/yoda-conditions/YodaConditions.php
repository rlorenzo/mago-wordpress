<?php

declare(strict_types=1);

// Ports WordPress.PHP.YodaConditionsUnitTest.inc.

// equals_variable_left
// @mago-expect lint:wordpress/yoda-conditions
if ($true == true) {
}

// equals_variable_right_is_yoda
if (false == $true) {
}

// not_equals_variable_left
// @mago-expect lint:wordpress/yoda-conditions
if ($true != true) {
}

// not_equals_variable_right_is_yoda
if (false != $true) {
}

// identical_variable_left
// @mago-expect lint:wordpress/yoda-conditions
if ($true === true) {
}

// identical_variable_right_is_yoda
if (false === $true) {
}

// not_identical_variable_left
// @mago-expect lint:wordpress/yoda-conditions
if ($true !== true) {
}

// not_identical_variable_right_is_yoda
if (false !== $true) {
}

// two_comparisons_on_one_condition_both_flagged
// @mago-expect lint:wordpress/yoda-conditions
// @mago-expect lint:wordpress/yoda-conditions
if ($true == true || $false == false) {
}

// function_call_on_the_left_is_excluded
if (strtolower($check) == $true) {
}

// cast_on_the_right_does_not_hide_a_bare_literal
if (true == (bool) $true) {
}

// string_literal_on_the_right
// @mago-expect lint:wordpress/yoda-conditions
if ($true == 'true') {
}

// string_literal_on_the_left_is_yoda
if ('false' == $true) {
}

// integer_literal_on_the_right
// @mago-expect lint:wordpress/yoda-conditions
if ($true == 0) {
}

// integer_literal_on_the_left_is_yoda
if (1 == $false) {
}

// class_constant_on_the_right
// @mago-expect lint:wordpress/yoda-conditions
if ($taxonomy === MyClass::TAXONOMY_SLUG) {
}

// class_constant_on_the_left_is_yoda
if (MyClass::TAXONOMY_SLUG === $taxonomy) {
}

// bare_constant_on_the_right
// @mago-expect lint:wordpress/yoda-conditions
if ($foo === FOO_CONSTANT) {
}

// bare_constant_on_the_left_is_yoda
if (FOO_CONSTANT === $foo) {
}

// both_sides_variables_are_exempt
if ($foo == $bar) {
}

// literal_on_the_left_inside_a_ternary_condition
$accessibility_mode = ('on' === sanitize_key($_GET['accessibility-mode'])) ? 'on' : 'off';

// self_static_property_on_the_right_is_exempt
if ($on !== self::$network_mode) {
}

// literal_on_the_left_in_a_return
return 0 === strpos($foo, 'a');

// zero_on_the_left_is_yoda_even_when_variable_side_is_missing
return 0 == $foo;

// variable_on_the_left_zero_on_the_right
// @mago-expect lint:wordpress/yoda-conditions
return $foo == 0;

// cast_array_access_both_sides_are_exempt
if ((int) $a['interval'] === (int) $b['interval']) {
}

// property_chain_ending_in_array_access_on_the_left
// @mago-expect lint:wordpress/yoda-conditions
if ($GLOBALS['wpdb']->num_rows === 0) {
}

// function_call_on_the_right_is_still_flagged
// @mago-expect lint:wordpress/yoda-conditions
if ($true == strtolower($check)) {
}

// method_call_on_the_left_is_excluded
$update = 'yes' === strtolower($this->from_post('update'));

// false_on_the_left_is_excluded_regardless_of_the_right_side
$sample = false !== strpos($link, '%pagename%');

// null_on_the_left_is_excluded
$sample = null !== strpos($link, '%pagename%');

// constant_on_the_left_is_excluded
$sample = SOME_CONSTANT !== strpos($link, '%pagename%');

// call_result_on_the_left_is_excluded
$sample = foo() !== strpos($link, '%pagename%');

// assignment_left_of_the_real_operand_is_outside_the_comparison
if ($sample = false !== strpos($link, '%pagename%')) {
}

// parenthesized_assignment_around_the_whole_condition_is_excluded
if ($sample = (false !== strpos($link, '%pagename%'))) {
}

// parenthesized_assignment_as_the_left_operand_is_excluded
if (($sample = false) !== strpos($link, '%pagename%')) {
}

// switch_case_variable_left
switch (true) {
    // @mago-expect lint:wordpress/yoda-conditions
    case $sample === 'something':
        break;

    case 'something' === $sample:
        break;
}

// for_loop_condition_variable_left
// @mago-expect lint:wordpress/yoda-conditions
for ($i = 0; $i !== 100; $i++) {
}

// for_loop_condition_literal_left_is_yoda
for ($i = 0; 100 != $i; $i++) {
}

// do_while_condition_variable_left
do {
    // @mago-expect lint:wordpress/yoda-conditions
} while ($sample === false);

// do_while_condition_constant_left_is_yoda
do {
} while (CONSTANT_A === $sample);

// while_condition_variable_left
// @mago-expect lint:wordpress/yoda-conditions
while ($sample === false) {
}

// while_condition_literal_left_is_yoda
while (false != $sample) {
}

// parenthesized_variable_on_the_left_is_excluded
$a = ($sample) === 'yes';

// nested_ternary_with_literal_operands_is_excluded
function is_windows(): bool
{
    return false !== ($test_is_windows = getenv('WP_CLI_TEST_IS_WINDOWS')) ? (bool) $test_is_windows : 0 === stripos(
        PHP_OS,
        'WIN',
    );
}

// variable_compared_to_a_parenthesized_ternary_is_flagged
// @mago-expect lint:wordpress/yoda-conditions
if (
    $something == (false !== ($test_is_windows = getenv('WP_CLI_TEST_IS_WINDOWS')) ? (bool) $test_is_windows : 0 === stripos(
        PHP_OS,
        'WIN',
    ))
) {
}

// parenthesized_ternary_compared_to_a_variable_is_excluded
if (
    (false !== ($test_is_windows = getenv('WP_CLI_TEST_IS_WINDOWS')) ? (bool) $test_is_windows : 0 === stripos(
        PHP_OS,
        'WIN',
    )) === $something
) {
}

// binary_cast_on_the_left_double_quoted_string_on_the_right
// @mago-expect lint:wordpress/yoda-conditions
if ((binary) $binary === b"binary $foo") {
}

// double_quoted_string_on_the_left_is_excluded
if (b"binary $foo" === (binary) $binary) {
}

// Right operand is a concatenation that starts with a variable: WPCS peeks at
// the first token after the operator, finds a variable, and exempts it.
if ($topic_slug === $topic_data['resource'] . '.' . $topic_data['event']) {
    echo 'ok';
}

// Right operand is a concatenation that starts with a literal: still flagged.
// @mago-expect lint:wordpress/yoda-conditions
if ($topic_slug === 'resource.' . $event) {
    echo 'flagged';
}

