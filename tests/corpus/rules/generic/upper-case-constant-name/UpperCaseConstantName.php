<?php

declare(strict_types=1);

namespace Corpus\UpperCaseConstantName;

// uppercase_names_are_fine
const MAX_ITEMS = 1;
define('MY_PLUGIN_VERSION', '1.0');

// lowercase_const_and_define_are_flagged
// @mago-expect lint:generic/upper-case-constant-name
const max_items = 1;
// @mago-expect lint:generic/upper-case-constant-name
define('FTP_OS_Unix', 'unix');

final class Limits
{
    // @mago-expect lint:generic/upper-case-constant-name
    public const perPage = 10;
}

// a_non_literal_name_is_skipped
define($name, 1);

// phpcs_ignore_silences_it
// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase
define('lower', 1);
