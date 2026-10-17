<?php

declare(strict_types=1);

// lowercase_types_and_class_names_pass
function lower(int $a, ?string $b, Foo\Bar $c, Bar|int $d): int|false {}

// every_reserved_type_in_a_parameter_or_return_type_is_flagged
// @mago-expect lint:generic/lowercase-type(6)
function upper(Int $a, ?STRING $b, Foo|FALSE $c, Self $d): Float|NULL {}

// closures_and_arrow_functions_are_checked
// @mago-expect lint:generic/lowercase-type(2)
$closure = function (Bool $a): INT {};
// @mago-expect lint:generic/lowercase-type(2)
$arrow = fn (?Callable $a): Float => 1;

// casts_are_checked
$ok = (int) $value;
// @mago-expect lint:generic/lowercase-type
$cast = (Int) $value;
// @mago-expect lint:generic/lowercase-type
$cast = ( Array ) $value;
// @mago-expect lint:generic/lowercase-type
$cast = (BInARY) $value;

// properties_and_constants_are_checked
class Typed
{
    // @mago-expect lint:generic/lowercase-type(2)
    public Int|ClassName|Float $prop;

    // @mago-expect lint:generic/lowercase-type(2)
    public function __construct(protected Float|Int $x) {}
}
