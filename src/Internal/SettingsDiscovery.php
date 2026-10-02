<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use InvalidArgumentException;
use Rlorenzo\MagoWordPress\Settings;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function implode;
use function is_array;
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

    /**
     * @param string $standard the WPCS standard to use when the project does not set one: the
     *     worker's `--standard=` argument, which the wordpress-core and wordpress-extra presets pass
     */
    public static function in(string $directory, string $standard = Settings::DEFAULT_STANDARD): Settings
    {
        $extra = self::composerExtra($directory . '/composer.json');
        if ($extra !== null) {
            return self::validated($extra + ['standard' => $standard], 'composer.json extra.mago-wordpress');
        }

        foreach (self::RULESETS as $name) {
            $ruleset = $directory . '/' . $name;
            if (file_exists($ruleset)) {
                $xml = (string) file_get_contents($ruleset);
                $values = PhpcsRuleset::values($xml, $directory, $name);
                // A ruleset built on a WPCS standard already excludes what that standard leaves out.
                $xpath = PhpcsRuleset::load($xml, $directory, $name);
                if ($xpath === null || PhpcsRuleset::standard($xpath) === null) {
                    $values['standard'] = $standard;
                }

                return self::validated($values, $name);
            }
        }

        return self::validated(['standard' => $standard], 'the worker command');
    }

    /**
     * @param array<array-key, mixed> $values
     * @throws InvalidArgumentException naming each invalid setting, one per line
     */
    private static function validated(array $values, string $source): Settings
    {
        $problems = Settings::problems($values);
        if ($problems !== []) {
            throw new InvalidArgumentException("{$source}: " . implode("\n{$source}: ", $problems));
        }

        return Settings::fromArray($values);
    }

    /**
     * Malformed JSON (e.g. a half-saved file), or no `extra.mago-wordpress` key at all,
     * counts as no settings and falls back to phpcs.xml. An explicitly present but empty
     * block (`{"extra": {"mago-wordpress": {}}}`) does not: it means "use the defaults".
     *
     * @return null|array<array-key, mixed>
     * @throws InvalidArgumentException when `extra.mago-wordpress` is not an object
     */
    private static function composerExtra(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $extra = Shape::arrayAt(json_decode((string) file_get_contents($path), associative: true), 'extra');
        if ($extra === null || !array_key_exists('mago-wordpress', $extra)) {
            return null;
        }

        if (!is_array($extra['mago-wordpress'])) {
            throw new InvalidArgumentException('composer.json extra.mago-wordpress: must be an object.');
        }

        return $extra['mago-wordpress'];
    }
}
