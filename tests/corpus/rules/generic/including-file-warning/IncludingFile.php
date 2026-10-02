<?php

declare(strict_types=1);

// unconditional_include_should_be_require
// @mago-expect lint:generic/including-file-warning
include 'blank.php';
// @mago-expect lint:generic/including-file-warning
include_once 'blank.php';

// inside_a_scope_a_condition_or_an_assignment_it_is_fine
if ($test) {
    include 'blank.php';
}

if (include_once 'blank.php') {
}

$loaded = include 'blank.php';
$config = ['a' => include 'blank.php'];

function load(): void
{
    include 'blank.php';
}

// phpcs_ignore_silences_it
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include 'blank.php';
