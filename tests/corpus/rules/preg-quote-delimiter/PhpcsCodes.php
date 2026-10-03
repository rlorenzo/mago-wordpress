<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.PHP.PregQuoteDelimiter.Missing
$pattern = preg_quote($word);

// @mago-expect lint:wordpress/preg-quote-delimiter
// phpcs:ignore WordPress.PHP.PregQuoteDelimiter.Found
$pattern = preg_quote($word);
