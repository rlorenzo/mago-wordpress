<?php

declare(strict_types=1);

// chained_assignment_is_flagged_once
// @mago-expect lint:generic/disallow-multiple-assignments
$a = $b = 1;

// assignment_in_a_condition_or_call_is_flagged
// @mago-expect lint:generic/disallow-multiple-assignments
if ($c = get_value()) {
}
// @mago-expect lint:generic/disallow-multiple-assignments
echo strtolower($d = 'X');

// while_conditions_for_initializers_defaults_and_properties_are_fine
while ($e = get_value()) {
}
for ($i = 0, $j = 1; $i < 10; $i++) {
}
function with_default($f = 1)
{
    $g = $f;
    $this_obj = new stdClass();
    $this_obj->prop = $g;

    return $this_obj;
}
final class Props
{
    public $h = 1;
    public const I = 2;
}
