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
}
