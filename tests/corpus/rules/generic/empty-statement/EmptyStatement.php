<?php

declare(strict_types=1);

function empty_statement_cases(bool $a, bool $b, array $list): void
{
    // empty_if_elseif_and_else_are_each_flagged
    // @mago-expect lint:generic/empty-statement(3)
    if ($a) {
        // Just a comment.
    } elseif ($b) {
    } else {
    }

    // empty_loops_are_flagged
    // @mago-expect lint:generic/empty-statement
    foreach ($list as $item) {}
    // @mago-expect lint:generic/empty-statement
    while ($a) { /* nothing */ }

    // empty_catch_is_flagged
    try {
        echo 1;
    // @mago-expect lint:generic/empty-statement
    } catch (\Exception $e) {
    }

    // colon_syntax_counts
    // @mago-expect lint:generic/empty-statement
    if ($a):
    endif;

    // an_unbraced_body_has_no_scope
    if ($a);

    // else_if_reports_the_inner_if_only
    if ($a) {
        echo 1;
    // @mago-expect lint:generic/empty-statement
    } else if ($b) {
    }

    // a_body_with_code_is_fine
    foreach ($list as $item) {
        echo $item;
    }

    // phpcs_ignore_silences_it
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedSwitch
    switch ($a) {
    }
}
