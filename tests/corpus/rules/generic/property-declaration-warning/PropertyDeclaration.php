<?php

declare(strict_types=1);

final class PropertyDeclarationWarningCases
{
    // leading_underscore_is_flagged_on_every_property_of_the_statement
    // @mago-expect lint:generic/property-declaration-warning(2)
    private $_a, $_b;

    // no_underscore_is_fine
    private $c;

    // phpcs_ignore_silences_it
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
    private $_d;
}
