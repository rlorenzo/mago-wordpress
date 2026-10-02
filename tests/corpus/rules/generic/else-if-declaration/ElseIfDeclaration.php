<?php

declare(strict_types=1);

function else_if_declaration(bool $a, bool $b): void
{
    // else_if_is_flagged
    if ($a) {
        echo 1;
    // @mago-expect lint:generic/else-if-declaration
    } else if ($b) {
        echo 2;
    }

    // elseif_and_plain_else_are_fine
    if ($a) {
        echo 1;
    } elseif ($b) {
        echo 2;
    } else {
        echo 3;
    }

    // a_comment_between_hides_the_if_like_the_sniff
    if ($a) {
        echo 1;
    } else /* note */ if ($b) {
        echo 2;
    }

    // phpcs_ignore_silences_it
    if ($a) {
        echo 1;
    // phpcs:ignore PSR2.ControlStructures.ElseIfDeclaration.NotAllowed
    } else if ($b) {
        echo 2;
    }
}
