<?php
/**
 * File.
 *
 * @package Fixture
 */

namespace Fixture;

/**
 * Throws what a factory method returns: self is not an exception name.
 */
final class Factory {
	/**
	 * Throws.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When it fails.
	 */
	public static function run() {
		try {
			self::go();
		} catch ( \LogicException $e ) {
			throw self::wrap( $e );
		}
	}
}
