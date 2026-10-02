<?php

declare(strict_types=1);

// parentheses_around_the_path
// @mago-expect lint:generic/including-file
require_once('blank.php');

// @mago-expect lint:generic/including-file
require ( 'blank.php' );

// a_comment_before_the_parenthesis_is_skipped_over
// @mago-expect lint:generic/including-file
require/* x */('blank.php');

// no_parentheses_is_fine
require_once 'blank.php';

// phpcs_ignore_silences_it
// phpcs:ignore PEAR.Files.IncludingFile.BracketsNotRequired
require('blank.php');
