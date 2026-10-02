<?php

declare(strict_types=1);

// assignment_in_if_is_flagged
// @mago-expect lint:generic/assignment-in-condition
if ($a = get_value()) {
}

// negated_assignment_is_flagged
// @mago-expect lint:generic/assignment-in-condition
if (!$count = get_value()) {
}

// nested_parenthesized_assignment_is_flagged
// @mago-expect lint:generic/assignment-in-condition
if ($a && ($ssl = get_value())) {
}

// each_assignment_after_a_boolean_operator_is_flagged
// @mago-expect lint:generic/assignment-in-condition(2)
while ($a = get_value() && $b .= 'x') {
}

// middle_part_of_for_and_case_are_checked
// @mago-expect lint:generic/assignment-in-condition
for ($i = 0; $i = 10; $i++) {
}
switch (true) {
    // @mago-expect lint:generic/assignment-in-condition
    case $a = 'x':
        break;
}

// comparisons_and_non_variable_left_sides_are_fine
if ($a === 1 || get_value() == $a) {
}
for ($i = 0; $i < 10; $i++) {
}
