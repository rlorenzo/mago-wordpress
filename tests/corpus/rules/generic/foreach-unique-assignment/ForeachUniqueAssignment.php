<?php

declare(strict_types=1);

// same_key_and_value_is_flagged
// @mago-expect lint:generic/foreach-unique-assignment
foreach ($items as $item => $item) {
}

// whitespace_and_comments_are_ignored
// @mago-expect lint:generic/foreach-unique-assignment
foreach ($items as $map['key'] => $map[ /* same */ 'key' ]) {
}

// reference_value_is_flagged
// @mago-expect lint:generic/foreach-unique-assignment
foreach ($items as $k => &$k) {
}

// key_reused_in_destructuring_is_flagged
// @mago-expect lint:generic/foreach-unique-assignment
foreach ($rows as $id => [$name, $id]) {
}

// @mago-expect lint:generic/foreach-unique-assignment
foreach ($rows as $id => list('id' => $id)) {
}

// distinct_variables_are_fine
foreach ($items as $key => $value) {
}

// duplicates_inside_the_list_are_not_this_rules_concern
foreach ($rows as $key => ['a' => $v, 'b' => $v]) {
}
