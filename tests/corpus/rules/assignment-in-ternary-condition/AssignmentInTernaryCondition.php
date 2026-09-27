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
