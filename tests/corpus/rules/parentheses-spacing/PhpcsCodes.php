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

// Too much space reports the sniff's Extra/TooMuch codes, not its missing-space ones.
// phpcs:ignore WordPress.WhiteSpace.ControlStructureSpacing.ExtraSpaceAfterOpenParenthesis, WordPress.WhiteSpace.ControlStructureSpacing.ExtraSpaceBeforeCloseParenthesis
if (  $x  ) {
}

// @mago-expect lint:wordpress/parentheses-spacing
// @mago-expect lint:wordpress/parentheses-spacing
// phpcs:ignore WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceAfterOpenParenthesis, WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBeforeCloseParenthesis
if (  $x  ) {
}

// phpcs:ignore WordPress.Arrays.ArrayKeySpacingRestrictions.TooMuchSpaceBeforeKey, WordPress.Arrays.ArrayKeySpacingRestrictions.TooMuchSpaceAfterKey
$v = $arr[  $i  ];

// A multi-line array reports the MultiLine codes.
// phpcs:ignore NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerMultiLine
$m = array(1,
	2 );
