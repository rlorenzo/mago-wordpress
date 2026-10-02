<?php

declare(strict_types=1);

// Any comment before the next case counts as a fall-through comment, so the
// test names sit above each switch.

// a_non_empty_case_falling_through_is_flagged_unless_commented
switch ($a) {
    // @mago-expect lint:generic/switch-declaration
    case 1:
        foo();
    case 2:
        foo();
        // Fall through.
    case 3:
    case 4:
        foo();
        break;
    default:
        foo();
}

// an_if_else_returning_on_every_path_ends_the_case_an_if_alone_does_not
switch ($a) {
    case 5:
        if ($b) {
            return 1;
        } else {
            return 2;
        }
    // @mago-expect lint:generic/switch-declaration
    case 6:
        if ($b) {
            return 1;
        }
    default:
        foo();
}

// phpcs_ignore_silences_it
switch ($a) {
    // phpcs:ignore PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
    case 7:
        foo();
    default:
        foo();
}
