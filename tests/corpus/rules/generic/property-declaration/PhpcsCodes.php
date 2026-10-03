<?php

declare(strict_types=1);

// A report carries the sniff's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

final class PropertyDeclarationCodes
{
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.ScopeMissing
    static $a;

    // @mago-expect lint:generic/property-declaration
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
    static $b;
}
