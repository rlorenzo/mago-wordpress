<?php

declare(strict_types=1);

// an_if_alone_in_an_else_block_is_flagged
if ($a) {
    echo 1;
// @mago-expect lint:generic/disallow-lonely-if
} else {
    if ($b) {
        echo 2;
    } elseif ($c) {
        echo 3;
    }
}

// alternative_syntax_is_flagged
if ($a):
    echo 1;
// @mago-expect lint:generic/disallow-lonely-if
else:
    if ($b):
        echo 2;
    endif;
endif;

// an_else_with_more_than_the_if_is_fine
if ($a) {
    echo 1;
} else {
    if ($b) {
        echo 2;
    }
    echo 3;
}

// elseif_and_unbraced_inner_if_are_fine
if ($a) {
    echo 1;
} elseif ($b) {
    echo 2;
} else {
    if ($c) echo 3;
}

// phpcs_ignore_silences_it
if ($a) {
    echo 1;
} else { // phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
    if ($b) {
        echo 2;
    }
}
