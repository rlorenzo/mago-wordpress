<?php

declare(strict_types=1);

// a_file_with_functions_and_a_class_is_flagged_once_at_the_later_first_declaration
function separate_functions_first(): void {}

if (!function_exists('separate_functions_second')) {
    function separate_functions_second(): void {}
}

// @mago-expect lint:generic/separate-functions-from-oo
final class SeparateFunctionsMixed
{
    public function method(): void {}
}
