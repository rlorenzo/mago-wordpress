<?php

declare(strict_types=1);

// standalone_post_increments_and_decrements_are_flagged
// @mago-expect lint:generic/disallow-standalone-post-increment-decrement
$i++;
// @mago-expect lint:generic/disallow-standalone-post-increment-decrement
$obj->items[$key]--;
// @mago-expect lint:generic/disallow-standalone-post-increment-decrement
self::$count++;

// pre_forms_and_expressions_are_fine
++$i;
--$obj->count;
$a = $i++;
for ($i = 0; $i < 3; $i++) {
}
foo($i++);

// statements_inside_parentheses_are_skipped_like_the_sniff_even_in_a_closure
return array_map(static function ($item) {
    $item++;

    return $item;
}, $items);

// operands_the_sniff_does_not_recognise_are_skipped
foo()->count++;
$obj->{$name}++;

// phpcs_ignore_silences_it
// phpcs:ignore Universal.Operators.DisallowStandalonePostIncrementDecrement.PostIncrementFound
$i++;
