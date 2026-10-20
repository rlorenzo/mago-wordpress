<?php

declare(strict_types=1);

class Fine extends ArrayObject implements Countable, Serializable {
}

abstract class FineAbstract {
}

// @mago-expect lint:generic/class-declaration
class  TwoSpaces {
}

// @mago-expect lint:generic/class-declaration
class SpaceAfterName  extends ArrayObject {
}

// @mago-expect lint:generic/class-declaration
class SpaceBeforeImplements extends ArrayObject  implements Countable {
}

// @mago-expect lint:generic/class-declaration
final  class TwoSpacesAfterFinal {
}

// @mago-expect lint:generic/class-declaration
class ExtendsOnNextLine
	extends ArrayObject {
}

class MultiLine implements
	Countable,
	// @mago-expect lint:generic/class-declaration
		Serializable {
}

class MultiLineSameLine implements
	Countable,
	// @mago-expect lint:generic/class-declaration
	Serializable, JsonSerializable {
}

// @mago-expect lint:generic/class-declaration
class BlankLineBeforeClose {
	public $a = 1;

}

// @mago-expect lint:generic/class-declaration
class CodeAfterClose {
}; echo 1;
