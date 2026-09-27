<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Rlorenzo\MagoWordPress\Settings;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Finds the project's settings: composer.json `extra.mago-wordpress` first, then the
 * WPCS properties in its phpcs.xml, so an existing phpcs setup keeps working unchanged.
 *
 * @internal
 */
final class SettingsDiscovery
{
    private const RULESETS = ['.phpcs.xml', 'phpcs.xml', '.phpcs.xml.dist', 'phpcs.xml.dist'];

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
     * @return null|array<string, mixed>
     */
    private static function composerExtra(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['extra'] ?? null)) {
            return null;
        }

        $settings = $decoded['extra']['mago-wordpress'] ?? null;
        if (!is_array($settings)) {
            return null;
        }

        $typed = [];
        foreach ($settings as $key => $value) {
            if (is_string($key)) {
                $typed[$key] = $value;
            }
        }

        return $typed;
    }
}
