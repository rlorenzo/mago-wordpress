<?php

declare(strict_types=1);

// in_array_with_true_is_ok
in_array(1, ['1', 1, true], true);

// in_array_missing_strict
// @mago-expect lint:wordpress/strict-in-array
in_array(1, ['1', 1, true]);

// in_array_false_is_still_a_warning
// @mago-expect lint:wordpress/strict-in-array
in_array(1, ['1', 1, true], false);

// case_insensitive_and_fully_qualified_name_still_matches
// @mago-expect lint:wordpress/strict-in-array
\In_Array(1, ['1', 1, true], false);

// method_calls_are_ignored
$foo->in_array(1, ['1', 1, true]);
Foo::in_array(1, ['1', 1, true]);

// too_few_arguments_is_a_warning
// @mago-expect lint:wordpress/strict-in-array
in_array(1, ['1', 1, true]);

// array_search_with_true_is_ok
array_search(1, $array, true);

// array_search_missing_strict
// @mago-expect lint:wordpress/strict-in-array
array_search(1, $array);

// array_keys_without_filter_value_does_not_need_strict
array_keys($array);
array_keys($array, 'my_key', true);

// array_keys_with_filter_value_missing_strict
// @mago-expect lint:wordpress/strict-in-array
array_keys($array, 'my_key');

// array_keys_with_filter_value_and_false_strict
// @mago-expect lint:wordpress/strict-in-array
array_keys($array, 'my_key', false);

// comments_between_arguments_are_ignored
in_array(1, ['1', 1], /* strict */ true);

// safeguard_php_8_named_arguments
in_array(strict: true, haystack: $haystack, needle: 1);

// named_strict_missing_still_warns
// @mago-expect lint:wordpress/strict-in-array
in_array(needle: 1, haystack: $haystack);

// named_filter_value_still_needs_strict
// @mago-expect lint:wordpress/strict-in-array
array_keys($array, filter_value: 'my_key');

// use_function_import_is_not_a_call
use function in_array;

// parenthesized_true_is_ok
in_array(1, ['1', 1, true], (true));
