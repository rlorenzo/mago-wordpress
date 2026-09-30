<?php

declare(strict_types=1);

// inner_loop_incrementing_the_outer_variable_is_flagged
// @mago-expect lint:generic/jumbled-incrementer
for ($i = 0; $i < 20; $i++) {
    for ($j = 0; $j < 5; $i++) {
        echo $j;
    }
}

// deeper_nesting_reports_once_per_inner_loop_and_the_middle_loop_too
// @mago-expect lint:generic/jumbled-incrementer(2)
for ($same = 0; $same < 20; $same++) {
    // @mago-expect lint:generic/jumbled-incrementer
    for ($j = 0; $j < 5; $same += 2) {
        for ($k = 0; $k > 3; $same++) {
            echo $k;
        }
    }
}

// colon_syntax_is_checked
// @mago-expect lint:generic/jumbled-incrementer
for ($n = 0; $n < 20; $n++) :
    for ($m = 0; $m < 5; $n += 2) :
    endfor;
endfor;

// distinct_incrementers_are_fine
for ($i = 0; $i < 20; $i++) {
    for ($j = 0; $j < 5; $j++) {
        echo $j;
    }
}

// outer_loop_without_braces_is_skipped_like_the_sniff
for ($i = 0; $i < 20; $i++) for ($j = 0; $j < 5; $i++) echo $j;

// outer_loop_without_an_incrementer_is_skipped
for ($i = 0; $i < 10;) {
    ++$i;
    for ($j = 0; $j < 5; $i++) {
    }
}

// phpcs_ignore_silences_it
// phpcs:ignore Generic.CodeAnalysis.JumbledIncrementer.Found
for ($i = 0; $i < 20; $i++) {
    for ($j = 0; $j < 5; $i++) {
    }
}
