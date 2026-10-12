<?php

/**
 * Fine.
 *
 * @param int    $a First.
 * @param string $b Second.
 * @return int
 */
function good( $a, $b ) {
	return $a;
}

// @mago-expect lint:generic/function-comment(2)
/**
 * Wrong names.
 *
 * @param int $x Mismatch.
 */
function names( $a ) {
}

// @mago-expect lint:generic/function-comment
/**
 * Extra and missing.
 *
 * @param int $a First.
 * @param int $b Extra.
 */
function extra( $a ) {
}

// @mago-expect lint:generic/function-comment
/**
 * Missing tag.
 *
 * @param int $a First.
 */
function missing( $a, $b ) {
}

// @mago-expect lint:generic/function-comment
/**
 * Void with value.
 *
 * @return void
 */
function void_value() {
	return 1;
}

// @mago-expect lint:generic/function-comment
/**
 * No return type.
 *
 * @return
 */
function no_type() {
	return 1;
}

// @mago-expect lint:generic/function-comment(3)
/**
 * Throws things.
 *
 * @throws
 * @throws exception on failure.
 * @throws Exception Whatever happens
 */
function thrower() {
}

// @mago-expect lint:generic/function-comment
/**
 * See.
 *
 * @see
 */
function seer() {
}

// @mago-expect lint:generic/function-comment(3)
/**
 * Type hints.
 *
 * @param integer $a Whole.
 * @param boolean $b Flag.
 * @param Foo|Bar $c Union.
 * @param Unknown_Type $d Odd.
 */
function hints( $a, $b, $c, $d ) {
}

// @mago-expect lint:generic/function-comment(7)
/**
 * Param bits.
 *
 * @param int
 * @param int $b
 * @param $c Missing type.
 * @param int $d No full stop
 * @param int &$e Reference.
 */
function bits( $a, $b, $c, $d, $e ) {
}

// @mago-expect lint:generic/function-comment
/**
 * Spacing.
 *
 * @param int $a Spaced.
 */

function spaced( $a ) {
}

// @mago-expect lint:generic/function-comment
/**
 * Duplicate.
 *
 * @return int
 * @return int
 */
function dup() {
	return 1;
}
