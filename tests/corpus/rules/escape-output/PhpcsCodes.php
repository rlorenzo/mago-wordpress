<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $title;

// @mago-expect lint:wordpress/escape-output
// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
echo $title;

// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
throw new Exception($message);

// phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction
_e('Plain text', 'my-plugin');

// Silencing UnsafePrintingFunction alone still checks the text argument, as in WPCS.
// @mago-expect lint:wordpress/escape-output
// phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction
_e($text, 'my-plugin');

// phpcs:ignore WordPress.Security.EscapeOutput
echo $title . $more;
