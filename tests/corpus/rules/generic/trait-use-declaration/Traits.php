<?php

declare(strict_types=1);

trait TraitA {
}

trait TraitB {
}

class Fine {

	use TraitA;
	use TraitB {
		TraitA::foo insteadof TraitB;
		TraitB::foo as bar;
	}

	public $a = 1;
}

class OnlyTraits {
	use TraitA;
}

// @mago-expect lint:generic/trait-use-declaration
class SameLine {

	use TraitA;
	use TraitB; use TraitA;

	public $a = 1;
}

// @mago-expect lint:generic/trait-use-declaration
class TwoInOne {

	use TraitA, TraitB;

	public $a = 1;
}

// @mago-expect lint:generic/trait-use-declaration
class NoBlankAfter {

	use TraitA;
	public $a = 1;
}

// @mago-expect lint:generic/trait-use-declaration
class BlankAtEnd {

	use TraitA;

}

// @mago-expect lint:generic/trait-use-declaration
class NotGrouped {

	use TraitA;
	public $a = 1;

	use TraitB;
}

// @mago-expect lint:generic/trait-use-declaration
class Group {

	use TraitA {
		TraitA::foo  insteadof TraitB;
		TraitB::foo as  bar ;
	}
}
