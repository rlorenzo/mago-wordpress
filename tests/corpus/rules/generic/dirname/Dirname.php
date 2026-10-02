<?php

declare(strict_types=1);

// file_constant_is_flagged
// @mago-expect lint:generic/dirname
$a = dirname(__FILE__);

// fully_qualified_and_with_levels
// @mago-expect lint:generic/dirname(2)
$b = \dirname(__FILE__) . dirname(__FILE__, 2);

// named_arguments
// @mago-expect lint:generic/dirname
$c = dirname(levels: 2, path: __FILE__);

// dir_is_fine_and_nested_calls_are_not_reported_like_wordpress_extra
$d = dirname(dirname(__DIR__));

// methods_and_other_paths_are_fine
$e = $obj->dirname(__FILE__) . dirname($path);

// phpcs_ignore_silences_it
// phpcs:ignore Modernize.FunctionCalls.Dirname.FileConstant
$f = dirname(__FILE__);
