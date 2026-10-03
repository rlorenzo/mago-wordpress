<?php

declare(strict_types=1);

// The test-name comments sit apart (blank line), since adjacent `//` lines are judged as one comment.

// prose_is_fine

// This function returns the value, unless it is not set.
function prose(): void {}

// a_commented_out_call_is_flagged

/** @mago-expect lint:generic/commented-out-code */
// do_something( $value );

// consecutive_line_comments_are_judged_together

/** @mago-expect lint:generic/commented-out-code */
// if ( $value ) {
//     return $value;
// }

// a_block_comment_is_judged_whole

/** @mago-expect lint:generic/commented-out-code */
/*
$value = get_option( 'name' );
update_option( 'name', $value + 1 );
*/

// a_tool_annotation_is_not_code

// @codeCoverageIgnore

// labels_like_note_are_not_code

// Note: see the docs for details.

/* phpcs_ignore_silences_it (a // comment here would take in the lines below, as phpcs does) */

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
// echo $value;
