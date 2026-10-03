<?php

declare(strict_types=1);

// unbraced_if_else_and_loops_are_flagged
// @mago-expect lint:generic/inline-control-structure(2)
if ($a) echo 1; else echo 2;

// @mago-expect lint:generic/inline-control-structure
foreach ($items as $item) echo $item;

// @mago-expect lint:generic/inline-control-structure
while ($a) $a--;

// @mago-expect lint:generic/inline-control-structure
do echo 3; while ($a);

// else_if_is_reported_on_the_if_and_elseif_on_its_own
if ($a) {
// @mago-expect lint:generic/inline-control-structure
} else if ($b) echo 4;
// @mago-expect lint:generic/inline-control-structure
elseif ($c) echo 5;

// braced_and_colon_bodies_are_fine
if ($a) {
    echo 6;
} elseif ($b) {
    echo 7;
} else {
    echo 8;
}

foreach ($items as $item):
    echo $item;
endforeach;

// empty_while_and_for_bodies_are_fine
while (next($items));
for ($i = 0; $i < 3; $i++);

// phpcs_ignore_silences_it
// phpcs:ignore Generic.ControlStructures.InlineControlStructure.NotAllowed
if ($a) echo 9;
