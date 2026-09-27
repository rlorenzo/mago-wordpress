<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Rlorenzo\MagoWordPress\Internal\Shape;

use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function strtolower;

/**
 * Project-level settings that WPCS reads from `phpcs.xml` properties.
 *
 * Mago does not pass custom rule options to extension workers, so the worker reads
 * these from the consuming project instead: `composer.json` (`extra.mago-wordpress`)
 * first, then the project's `phpcs.xml`, so an existing WPCS setup keeps working.
 *
 * @api
 */
final class Settings
{
    private const DEFAULT_MINIMUM_WP_VERSION = '6.0';

    /**
     * Keys accepted in composer.json `extra.mago-wordpress` for the custom function lists,
     * mapped to the WPCS property they mirror.
     */
    public const CUSTOM_LISTS = [
        'custom-escaping-functions' => 'customEscapingFunctions',
        'custom-auto-escaped-functions' => 'customAutoEscapedFunctions',
        'custom-sanitizing-functions' => 'customSanitizingFunctions',
        'custom-unslashing-sanitizing-functions' => 'customUnslashingSanitizingFunctions',
        'custom-capabilities' => 'custom_capabilities',
    ];

    /**
     * @param list<string> $textDomains
     * @param list<string> $prefixes
     * @param array<string, list<string>> $customLists keyed by composer.json option name
     */
    public function __construct(
        public readonly array $textDomains = [],
        public readonly array $prefixes = [],
        public readonly string $minimumWpVersion = self::DEFAULT_MINIMUM_WP_VERSION,
        public readonly array $customLists = [],
    ) {}

    /**
     * @return list<string>
     */
    public function customList(string $option): array
    {
        return $this->customLists[$option] ?? [];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $customLists = [];
        foreach (self::CUSTOM_LISTS as $option => $_property) {
            // Capability names are case-sensitive in WordPress; function names are not.
            $list = self::stringList($values[$option] ?? []);
            $customLists[$option] = $option === 'custom-capabilities' ? $list : self::lowercased($list);
        }

        return new self(
            textDomains: self::unique(self::stringList($values['text-domains'] ?? [])),
            prefixes: self::lowercased(self::stringList($values['prefixes'] ?? [])),
            minimumWpVersion: Shape::string($values['minimum-wp-version'] ?? null) ?? self::DEFAULT_MINIMUM_WP_VERSION,
            customLists: $customLists,
        );
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }

            $strings[] = $item;
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param list<string> $list
     * @return list<string>
     */
    private static function lowercased(array $list): array
    {
        return self::unique(array_map(strtolower(...), $list));
    }

    /**
     * Text domains are compared exactly, as WPCS does, so their case is kept.
     *
     * @param list<string> $list
     * @return list<string>
     */
    private static function unique(array $list): array
    {
        return array_values(array_unique($list));
    }
}
