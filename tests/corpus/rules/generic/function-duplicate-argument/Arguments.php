<?php

declare(strict_types=1);

// A repeated parameter name is a parse-time error in PHP, so tests/Unit/RuleFixesTest covers the report.
function ok($a, $b, $c) {}

abstract class Foo {
    public function method(int $a, string $b = 'x', $c = null) {}

    abstract public function declared($x, $y);
}

$closure = function ($a, &$b) use ($z) {};

$arrow = fn($a) => $a;
