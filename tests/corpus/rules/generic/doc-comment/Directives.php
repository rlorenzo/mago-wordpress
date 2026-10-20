<?php
/**
 * File.
 *
 * @package Fixture
 */

namespace Fixture;

// @mago-expect lint:generic/doc-comment
/**
 * phpcs:ignore Generic.Files.LineLength
 *
 * @param int $a First.
 *
 * @return int
 */
function directive_line_is_not_a_description( $a ) {
	return $a;
}

// @mago-expect lint:generic/doc-comment
/***
 * @return int
 */
function triple_star_opener() {
	return 1;
}

// @mago-expect lint:generic/doc-comment
/**
 * Directive after a tag group.
 *
 * @param int $a First.
 * phpcs:ignore Squiz.Commenting.FunctionComment.ExtraParamComment
 * @param int $b Second.
 *
 * @return int
 */
function directive_after_tag_group( $a, $b ) {
	return $a + $b;
}
