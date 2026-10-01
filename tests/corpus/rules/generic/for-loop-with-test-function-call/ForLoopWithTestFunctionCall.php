<?php

declare(strict_types=1);

// function_call_in_test_part_is_flagged
// @mago-expect lint:generic/for-loop-with-test-function-call
for ($i = 0; $i < count($items); $i++) {
    echo $i;
}

// method_call_in_test_part_is_flagged
// @mago-expect lint:generic/for-loop-with-test-function-call
for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
    echo $iterator->current();
}

// qualified_and_variable_callees_are_flagged
// @mago-expect lint:generic/for-loop-with-test-function-call
for ($i = 0; $i < \count($items); $i++) {
}

// @mago-expect lint:generic/for-loop-with-test-function-call
for ($i = 0; $callback($i); $i++) {
}

// calls_in_the_init_or_increment_parts_are_fine
for ($i = 0, $c = count($items); $i < $c; $i = next_index($i)) {
    echo $i;
}

// language_constructs_are_not_calls
for ($i = 0; isset($items[$i]); $i++) {
    echo $i;
}
