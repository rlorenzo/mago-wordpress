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
 * @throws \Exception When it fails.
 */
function fine() {
	throw new \Exception( 'x' );
}

// @mago-expect lint:generic/function-comment-throw-tag
/**
 * No tag.
 */
function missing_tag() {
	throw new \Exception( 'x' );
}

// @mago-expect lint:generic/function-comment-throw-tag
/**
 * Two exceptions, one tag.
 *
 * @throws \Exception When it fails.
 */
function wrong_number( $a ) {
	if ( $a ) {
		throw new \Exception( 'x' );
	}
	throw new \RuntimeException( 'y' );
}

// @mago-expect lint:generic/function-comment-throw-tag
/**
 * Another class than the tag names.
 *
 * @throws \LogicException When it fails.
 */
function other_class() {
	throw new \RuntimeException( 'y' );
}

/**
 * A closure's throw belongs to the closure.
 *
 * @return callable
 */
function closure_throws() {
	return function () {
		throw new \Exception( 'x' );
	};
}

/**
 * Rethrown from a catch block.
 *
 * @throws \Exception When it fails.
 */
function rethrown() {
	try {
		do_something();
	} catch ( \Exception $e ) {
		throw $e;
	}
}

/**
 * A throw inside an anonymous class method belongs to that method.
 *
 * @return object
 */
function anonymous_class_throws() {
	return new class() {
		/**
		 * Documented.
		 *
		 * @throws \Exception When it fails.
		 */
		public function run() {
			throw new \Exception( 'x' );
		}
	};
}

/**
 * Rethrown from a union catch block: each type needs its own tag.
 *
 * @throws \Exception When it fails.
 * @throws \Error     When it fails badly.
 */
function rethrown_union() {
	try {
		do_something();
	} catch ( \Exception | \Error $e ) {
		throw $e;
	}
}
