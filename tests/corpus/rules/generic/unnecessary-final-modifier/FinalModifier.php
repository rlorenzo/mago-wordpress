<?php

class FixtureFinalOpen {
	public final function final_method() {}
	final public const FINAL_CONSTANT = 1;
}

final class FixtureFinalClass {
	public $plain;
	// @mago-expect lint:generic/unnecessary-final-modifier
	public final function final_method() {}
	// @mago-expect lint:generic/unnecessary-final-modifier
	final protected function other_method() {}
	protected function plain_method() {}
	// @mago-expect lint:generic/unnecessary-final-modifier
	final public const FINAL_CONSTANT = 'a';
	protected const PLAIN_CONSTANT = 'b';
	// @mago-expect lint:generic/unnecessary-final-modifier
	protected final $property = 'c';
}

abstract class FixtureAbstract {
	final public function final_method() {}
}
