<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Rlorenzo\MagoWordPress\Settings;

use function file_exists;
use function file_get_contents;
use function json_decode;

/**
 * Finds the project's settings: composer.json `extra.mago-wordpress` first, then the
 * WPCS properties in its phpcs.xml, so an existing phpcs setup keeps working unchanged.
 *
 * @internal
 */
final class SettingsDiscovery
{
    public const RULESETS = ['.phpcs.xml', 'phpcs.xml', '.phpcs.xml.dist', 'phpcs.xml.dist'];

    private function __construct() {}

    public static function in(string $directory): Settings
    {
        $extra = self::composerExtra($directory . '/composer.json');
        if ($extra !== null) {
            return Settings::fromArray($extra);
        }

        foreach (self::RULESETS as $name) {
            $ruleset = $directory . '/' . $name;
            if (file_exists($ruleset)) {
                return Settings::fromArray(PhpcsRuleset::values((string) file_get_contents($ruleset)));
            }
        }

        return new Settings();
    }

    /**
     * Malformed JSON (e.g. a half-saved file), or no `extra.mago-wordpress` key at all,
     * counts as no settings and falls back to phpcs.xml. An explicitly present but empty
     * block (`{"extra": {"mago-wordpress": {}}}`) does not: it means "use the defaults".
     *
     * @return null|array<array-key, mixed>
     */
    private static function composerExtra(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        return Shape::arrayAt(
            json_decode((string) file_get_contents($path), associative: true),
            'extra',
            'mago-wordpress',
        );
    }
}
