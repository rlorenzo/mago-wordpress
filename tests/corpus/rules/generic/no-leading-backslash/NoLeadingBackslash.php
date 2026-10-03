<?php

declare(strict_types=1);

namespace Corpus\NoLeadingBackslash;

// leading_backslash_is_flagged
// @mago-expect lint:generic/no-leading-backslash
use \Foo\Bar;

// each_item_and_function_imports
// @mago-expect lint:generic/no-leading-backslash(2)
use function \foo, \Bar\baz;

// group_prefix
// @mago-expect lint:generic/no-leading-backslash
use \Grouped\{A, B};

// plain_imports_are_fine
use Foo\Baz;
use function Foo\qux;

// trait_use_is_not_an_import
final class UsesTrait
{
    use \Some\TraitName;
}

// phpcs_ignore_silences_it
// phpcs:ignore Universal.UseStatements.NoLeadingBackslash.LeadingBackslashFound
use \Ignored\Name;
