<?php

declare(strict_types=1);

// count_in_for_test_part_is_flagged
// @mago-expect lint:generic/disallow-size-functions-in-loops
for ($i = 0; $i < count($items); $i++) {
}

// strlen_in_while_condition_is_flagged
// @mago-expect lint:generic/disallow-size-functions-in-loops
while ($i < strlen($text)) {
    $i++;
}

// sizeof_in_do_while_condition_is_flagged
do {
    $i++;
    // @mago-expect lint:generic/disallow-size-functions-in-loops
} while ($i < sizeof($items));

// qualified_name_is_flagged
// @mago-expect lint:generic/disallow-size-functions-in-loops
while ($i < \count($items)) {
    $i++;
}

// init_part_of_for_is_fine
for ($i = count($items); $i > 0; $i--) {
}

// property_named_count_is_fine
while ($i < $list->count) {
    $i++;
}

// match_is_case_sensitive_like_the_sniff
while ($i < Count($items)) {
    $i++;
}
