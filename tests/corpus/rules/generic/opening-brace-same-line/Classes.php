<?php

declare(strict_types=1);

class Fine {
}

class FineAndEmpty {}

interface FineToo extends Countable {
}

class Wrapped
    extends Fine {
}

class Allman
// @mago-expect lint:generic/opening-brace-same-line
{
}

trait AllmanTrait
// @mago-expect lint:generic/opening-brace-same-line
{
}

// @mago-expect lint:generic/opening-brace-same-line
class WithCode { public $a = 1;
}

// @mago-expect lint:generic/opening-brace-same-line
class TooMuchSpace  {
}

// @mago-expect lint:generic/opening-brace-same-line
class NoSpace{
}
