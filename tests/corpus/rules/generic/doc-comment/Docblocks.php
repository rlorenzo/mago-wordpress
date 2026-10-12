<?php
/**
 * File.
 *
 * @package Fixture
 */

namespace Fixture;

/**
 * Fine.
 *
 * Long description.
 *
 * @param int $a First.
 *
 * @return int
 */
function fine( $a ) {
	return $a;
}

// @mago-expect lint:generic/doc-comment
/**
 * @return int
 */
function missing_short() {
	return 1;
}

// @mago-expect lint:generic/doc-comment
/**
 * short in lower case.
 */
function lower_short() {
}

// @mago-expect lint:generic/doc-comment(2)
/**
 * Short.
 *
 *
 * long in lower case.
 */
function long_description() {
}

// @mago-expect lint:generic/doc-comment
/**
 * Short.
 * @return int
 */
function tags_without_blank() {
	return 1;
}

// @mago-expect lint:generic/doc-comment
/**
 * Short.
 *
 * @return int
 *
 */
function blank_at_end() {
	return 1;
}

// @mago-expect lint:generic/doc-comment
/**
 */
function empty_docblock() {
}
