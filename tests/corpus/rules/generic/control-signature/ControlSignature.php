<?php

declare(strict_types=1);

// a_comment_or_newline_between_the_brace_and_else_is_flagged
if ($a) {
    echo 1;
// @mago-expect lint:generic/control-signature
} // the usual case
else {
    echo 2;
}

try {
    echo 3;
// @mago-expect lint:generic/control-signature
}
catch (Exception $e) {
    echo 4;
// @mago-expect lint:generic/control-signature
}finally {
    echo 5;
}

do {
    echo 6;
// @mago-expect lint:generic/control-signature
}  while ($a);

// one_space_is_fine
if ($a) {
    echo 1;
} elseif ($b) {
    echo 2;
} /* inline */ else {
    echo 3;
}

// a_single_tab_counts_as_one_space_as_in_phpcs
if ($a) {
    echo 1;
}	else {
    echo 2;
}

// phpcs_ignore_silences_it
if ($a) {
    echo 1;
} // phpcs:ignore Squiz.ControlStructures.ControlSignature.SpaceAfterCloseBrace
else {
    echo 2;
}
