<?php

declare(strict_types=1);

function unconditional_if(bool $a): void
{
    // literal_conditions_are_flagged
    // @mago-expect lint:generic/unconditional-if-statement
    if (true) {
        echo 1;
    // @mago-expect lint:generic/unconditional-if-statement
    } elseif (\FALSE /* off */) {
        echo 2;
    }

    // anything_else_passes_like_the_sniff
    if ($a) {
        echo 3;
    }

    if (true || $a) {
        echo 4;
    }

    if ((false)) {
        echo 5;
    }

    // phpcs_ignore_silences_it
    // phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found
    if (false) {
        echo 6;
    }
}
