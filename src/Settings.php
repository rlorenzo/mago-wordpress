<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

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
        public readonly string $minimumWpVersion = '6.0',
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
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $customLists = [];
        foreach (self::CUSTOM_LISTS as $option => $_property) {
            $customLists[$option] = self::stringList($values[$option] ?? []);
        }

        $version = $values['minimum-wp-version'] ?? null;

        return new self(
            textDomains: self::stringList($values['text-domains'] ?? []),
            prefixes: self::stringList($values['prefixes'] ?? []),
            minimumWpVersion: is_string($version) ? $version : '6.0',
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

            $strings[] = strtolower($item);
        }

        return $strings;
    }
}
