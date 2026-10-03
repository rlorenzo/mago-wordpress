<?php

declare(strict_types=1);

// the_first_declaration_is_fine
interface Greeter
{
}

// a_second_one_is_flagged
// @mago-expect lint:generic/one-object-structure-per-file
final class Hello implements Greeter
{
}

// conditional_declarations_count_too
if (PHP_VERSION_ID > 80000) {
    // @mago-expect lint:generic/one-object-structure-per-file
    trait Polyfill
    {
    }
}

// anonymous_classes_do_not_count
$x = new class {};

// phpcs_ignore_silences_it
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
enum Suit
{
}
