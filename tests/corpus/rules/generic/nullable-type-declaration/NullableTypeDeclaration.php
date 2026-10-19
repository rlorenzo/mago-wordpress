<?php

class NullableTypes
{
    public function valid(?array $array = null, ?MyObject $object = null): ?MyObject
    {
    }

    // @mago-expect lint:generic/nullable-type-declaration
    public function tooMuchWhitespace(? array $array = null, ?MyObject $object = null): ? MyObject
    {
    }

    // @mago-expect lint:generic/nullable-type-declaration
    public function withFqn(? \MyNameSpace\MyArray $array = null): ? \MyNameSpace\MyObject
    {
    }

    // @mago-expect lint:generic/nullable-type-declaration
    public function withComment(? /* a comment, fixing is undesirable */ array $varB): void
    {
    }

    // @mago-expect lint:generic/nullable-type-declaration
    public function withNewline(?
        string $varA): void
    {
    }
}

// valid in lambda
array_map(function (?int $integer = null): ?int {
    return $integer;
}, []);

// invalid in lambda
// @mago-expect lint:generic/nullable-type-declaration
// @mago-expect lint:generic/nullable-type-declaration
array_map(function (? int $integer = null): ? int {
    return $integer;
}, []);

// @mago-expect lint:generic/nullable-type-declaration
function testKeywords(? self $self, ? callable $callable): ? static {}

// A ternary is not a nullable type.
$test = Something::one(self::CONSTANT) ?: '';
$foo = new static(is_null($a) ? foo($a) : $a, is_null($b) ? $b : $c);
$bar = $value instanceof static ? function_call($value) : $value;
