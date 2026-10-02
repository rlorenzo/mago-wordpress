<?php

declare(strict_types=1);

// sizeof_and_delete_are_flagged
// @mago-expect lint:generic/forbidden-functions(2)
$n = sizeof($items) + \SizeOf($items);
// @mago-expect lint:generic/forbidden-functions
delete($file);

// methods_and_count_are_fine
$n = $obj->sizeof() + count($items);

// phpcs_ignore_silences_it
// phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
$n = sizeof($items);
