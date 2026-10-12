<?php
/**
 * File.
 *
 * @package Fixture
 */

namespace Fixture;

/**
 * Class.
 */
class Props {
	/**
	 * Fine.
	 *
	 * @var int
	 */
	public $fine = 1;

	/** @var string Short form. */
	public $short = '';

	// @mago-expect lint:generic/variable-comment
	/**
	 * No var tag.
	 */
	public $no_var = 1;

	// @mago-expect lint:generic/variable-comment
	/**
	 * Empty var.
	 *
	 * @var
	 */
	public $empty_var = 1;

	// @mago-expect lint:generic/variable-comment(2)
	/**
	 * Two vars and an empty see.
	 *
	 * @var int
	 * @var string
	 * @see
	 */
	public $dup = 1;

	// @mago-expect lint:generic/variable-comment
	/**
	 * Past an attribute.
	 */
	#[Deprecated]
	protected static ?int $attr = null;
}
