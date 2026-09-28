<?php

declare(strict_types=1);

// comparison_in_parens_is_ok
$mode = ($a == 'something') ? 'on' : 'off';

// chained_ternaries_without_assignment_are_ok
$mode = ($a == 'on' ? 'true' : ($a == 'off' ? 't' : 'f'));

// assignment_without_parens_is_not_checked
$mode = $a = ('on' ? 'on' : 'off');

// simple_assignment_in_parens
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a = 'on') ? 'on' : 'off';

// elvis_operator_is_checked_too
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a = 'on') ?: 'off';

// chained_assignment_reports_each_variable
// @mago-expect lint:wordpress/assignment-in-ternary-condition
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a = $b = 'on') ? 'on' : 'off';

// array_element_assignment
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a['key'] = 'on') ? 'on' : 'off';

// property_assignment
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a->prop = 'on') ? 'on' : 'off';

// static_property_assignment
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (Foo::$prop = 'on') ? 'on' : 'off';

// null_coalesce_assignment_is_checked_too
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a ??= 'on') ? 'on' : 'off';

// assignment_target_is_a_call_is_ignored
$mode = (getTarget() = true) ? 'on' : 'off';

// closures_inside_other_calls_are_unaffected
call_user_func(function () {
    $foo = 42;

    return 1 === 2 ? 'a' : 'b';
});

// whole_ternary_in_parens_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a = 'on' ? 'on' : 'off');

// whole_elvis_in_parens_with_indirect_variable_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (${$a->prop} = 'on' ?: 'off');

// parenthesized_condition_and_nested_whole_ternary_report_one_each
// @mago-expect lint:wordpress/assignment-in-ternary-condition
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a = 'on') ? 'true' : ($a = 'off' ? 't' : ${$a->prop});

// whole_ternary_and_nested_whole_ternary_report_one_each
// @mago-expect lint:wordpress/assignment-in-ternary-condition
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = ($a = 'on' ? 'true' : ($a = 'off' ? 't' : 'f'));

// whole_ternary_as_only_call_argument_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
foo($a = 'on' ? 'on' : 'off');

// whole_ternary_as_control_structure_condition_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
if ($a = 'on' ? true : false) {
}

// @mago-expect lint:wordpress/assignment-in-ternary-condition
while ($a = 'on' ? true : false) {
}

// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = match ($a = 'on' ? 1 : 2) {
    default => 'x',
};

// whole_ternary_as_only_named_argument_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
foo(mode: $a = 'on' ? 'on' : 'off');

// whole_ternary_among_several_call_arguments_is_not_checked
foo($a = 'on' ? 'on' : 'off', 1);

// ternary_without_any_parens_is_not_checked
$mode = $a = 'on' ? 'on' : 'off';
$mode = $a = 'on' ?: 'off';

// closure_in_parens_is_unaffected
(function () {
    $foo = 42;

    return 1 === 2 ? 'a' : 'b';
});

// ternary_inside_closure_argument_is_unaffected
$content = preg_replace_callback(
    '/x/',
    function ($matches) {
        $rowcount = substr_count($table, '<tr>');
        $height = $rowcount < 6 ? '100' : '250';
    },
    $content,
);

// array_value_ternary_is_not_checked
$array = [
    'key' => $key = true ?: $alt,
];

// condition_ending_in_call_parens_scans_only_those_parens
// WPCS bounds the condition by the call's own parentheses, which hold no assignment.
$mode = ($a = foo() ? 'on' : 'off');

// The upstream fixture's unparenthesized nested ternaries (`$a ? b : $c ? d : e`)
// are a fatal error since PHP 8.0 and are not ported.

// The cases below cover an assignment joined to the rest of the enclosing
// parentheses by an operator, rather than being the whole content of, or the
// direct value of, those parentheses.

// assignment_after_logical_and_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (true && $a = 'on' ? 'on' : 'off');

// assignment_after_logical_or_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (false || $a = 'on' ? 'on' : 'off');

// assignment_after_and_keyword_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (true and $a = 'on' ? 'on' : 'off');

// assignment_after_or_keyword_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (false or $a = 'on' ? 'on' : 'off');

// assignment_after_xor_keyword_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (true xor $a = 'on' ? 'on' : 'off');

// assignment_after_comparison_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (1 == $a = 'on' ? 'on' : 'off');

// assignment_after_arithmetic_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (1 + $a = 1 ? 'on' : 'off');

// assignment_after_unary_not_is_checked
// @mago-expect lint:wordpress/assignment-in-ternary-condition
$mode = (!$a = 'on' ? 'on' : 'off');

// assignment_outside_the_enclosing_parentheses_is_not_checked
// $a is assigned outside the ternary's own bounding parentheses, so it is
// unrelated to the ternary even though `&&` joins the two expressions.
$mode = ($a = 'on') && (true ? 'on' : 'off');

// whole_ternary_with_intervening_operator_among_several_call_arguments_is_not_checked
foo(true && $a = 'on' ? 'on' : 'off', 1);
