<?php

declare(strict_types=1);

// silenced_call_is_flagged
// @mago-expect lint:wordpress/no-silenced-errors
$value = @some_userland_function($param);

// the_full_standard_does_not_use_the_php_function_list (WordPress-Extra sets usePHPFunctionsList false)
// @mago-expect lint:wordpress/no-silenced-errors
$exists = @is_file($path);

// silenced_variable_and_static_call_are_flagged
// @mago-expect lint:wordpress/no-silenced-errors
$file = @$obj->read($file);
// @mago-expect lint:wordpress/no-silenced-errors
$file = @MyClass::file_get_contents($file);

// at_sign_in_a_string_or_comment_is_not_an_operator
echo '@';
// @see nothing
