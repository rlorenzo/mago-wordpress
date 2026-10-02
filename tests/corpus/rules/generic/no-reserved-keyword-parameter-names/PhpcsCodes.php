<?php

declare(strict_types=1);

// The message code carries the keyword, as the sniff's does.

// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.listFound
function ignored_list($list): void {}

// @mago-expect lint:generic/no-reserved-keyword-parameter-names
// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
function other_code($list): void {}
