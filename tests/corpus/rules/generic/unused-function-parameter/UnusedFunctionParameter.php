<?php

declare(strict_types=1);

// only_the_parameters_after_the_last_used_one_are_reported_not_b
// @mago-expect lint:generic/unused-function-parameter
function callback($a, $b, $c, $d)
{
    return $a * $c;
}

// a_lone_unused_parameter
// @mago-expect lint:generic/unused-function-parameter
function lone($a)
{
    return 'foobar';
}

// uses_in_strings_heredocs_and_nested_closures_count
function in_string($a, $b, $c)
{
    echo "{$a}";
    echo <<<TXT
        $b
        TXT;
    return static fn() => $c;
}

// closures_and_arrow_functions_are_checked
// @mago-expect lint:generic/unused-function-parameter
$closure = static fn($a, $b) => $a;

// an_empty_body_is_skipped
function empty_body($a) {}

class Plain
{
    // methods_of_a_plain_class_are_checked
    // @mago-expect lint:generic/unused-function-parameter
    public function method($a, $b)
    {
        return $a;
    }

    // magic_methods_with_a_fixed_signature_are_skipped
    public function __get(string $name)
    {
        return null;
    }

    // promoted_parameters_are_skipped
    public function __construct(
        private int $id,
    ) {
        doSomething();
    }
}

class Child extends Plain
{
    // methods_of_a_class_that_extends_are_skipped_like_wordpress_extra
    public function other($a, $b)
    {
        return $a;
    }
}

// phpcs_ignore_silences_it
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
function ignored($a)
{
    return 1;
}
