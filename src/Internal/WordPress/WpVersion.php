<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use function version_compare;

/**
 * Comparisons against the `minimum-wp-version` setting.
 *
 * @internal
 */
final class WpVersion
{
    private function __construct() {}

    /**
     * Whether the project's oldest supported WordPress version is at least
     * $version. $minimum is `Settings::normalizedMinimumWpVersion()`; null
     * (an empty or unparsable setting) counts as reached, matching WPCS,
     * which then reports every deprecation and assumes current features.
     */
    public static function reached(?string $minimum, string $version): bool
    {
        return $minimum === null || version_compare($minimum, $version, operator: '>=');
    }

    /**
     * A note for a deprecation the project's minimum WordPress version has not
     * reached, or NULL once it has. WPCS reports such a usage as a warning
     * instead of an error; the rules here report it with this note, since a
     * Mago issue carries the rule's level.
     */
    public static function pendingNote(?string $minimum, string $version): ?string
    {
        if (self::reached($minimum, $version)) {
            return null;
        }

        return "Deprecated after the configured minimum-wp-version ({$minimum}); WPCS reports this as a warning until the minimum reaches {$version}.";
    }
}
