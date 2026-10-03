<?php

declare(strict_types=1);

// plain_double_quoted_strings_are_flagged
// @mago-expect lint:generic/double-quote-usage
$a = "plain";
// @mago-expect lint:generic/double-quote-usage
$b = "say \"hi\" for \$5";

// strings_that_need_double_quotes_are_fine
$c = "line\n";
$d = "it's";
$e = "Hello $name";
$f = 'single';

// phpcs_ignore_silences_it
// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
$g = "plain";
