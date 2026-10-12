<?php
/**
 * File.
 *
 * @package Fixture
 */

namespace Fixture;

/**
 * Aligned.
 *
 * @return int
 */
function aligned() {
	return 1;
}

// @mago-expect lint:generic/doc-comment-alignment(2)
/**
*Misaligned and no space.
 */
function misaligned() {
}

// @mago-expect lint:generic/doc-comment-alignment
/**
   * Too far in.
 */
function too_far() {
}

/**
 * Not a declaration's docblock, so it is not checked.
  */
echo 1;

// <?php
/**
 * A mention of the opening tag in a comment is not a file comment.
  * Misaligned, but not documenting a declaration.
 */

/**
 * A call whose name starts with a keyword is not a declaration.
  */
classé();
