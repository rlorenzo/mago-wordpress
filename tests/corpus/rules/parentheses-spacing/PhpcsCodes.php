<?php

declare(strict_types=1);

// A report carries the phpcs message code of the sniff it stands in for.

// phpcs:ignore PEAR.Functions.FunctionCallSignature
foo($a);

// phpcs:ignore WordPress.WhiteSpace.ControlStructureSpacing
if ($x) {
}

// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
// phpcs:ignore WordPress.WhiteSpace.ControlStructureSpacing
foo($a);
