<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Rlorenzo\MagoWordPress\Internal\Shape;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Project-level settings that WPCS reads from `phpcs.xml` properties.
 *
 * Mago does not pass custom rule options to extension workers, so the worker reads
 * these from the consuming project instead: `composer.json` (`extra.mago-wordpress`)
 * first, then the project's `phpcs.xml`, so an existing WPCS setup keeps working.
 *
 * @api
 * @mago-expect lint:cyclomatic-complexity
 */
final class Settings
{
    private const DEFAULT_MINIMUM_WP_VERSION = '6.0';

    private const DEFAULT_MAX_POSTS_PER_PAGE = 100;

    private const DEFAULT_MIN_CRON_INTERVAL = 900;

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
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        public readonly array $textDomains = [],
        public readonly array $prefixes = [],
        public readonly string $minimumWpVersion = self::DEFAULT_MINIMUM_WP_VERSION,
        public readonly array $customLists = [],
        public readonly int $maxPostsPerPage = self::DEFAULT_MAX_POSTS_PER_PAGE,
        public readonly int $minCronInterval = self::DEFAULT_MIN_CRON_INTERVAL,
        public readonly string $additionalWordDelimiters = '',
        public readonly bool $honorPhpcsComments = true,
    ) {}

    /**
     * @return list<string>
     */
    public function customList(string $option): array
    {
        return $this->customLists[$option] ?? [];
    }

    /**
     * The minimum WordPress version padded to `major.minor.patch`, or NULL
     * when it is empty or unparsable.
     *
     * `version_compare()` treats a shorter version as older than the same
     * version with a trailing `.0` (`"4.5" < "4.5.0"`), hence the padding.
     */
    public function normalizedMinimumWpVersion(): ?string
    {
        $parts = [];
        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', trim($this->minimumWpVersion), $parts) !== 1) {
            return null;
        }

        return ($parts[1] ?? '0') . '.' . ($parts[2] ?? '0') . '.' . ($parts[3] ?? '0');
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        // A negative maximum is invalid and falls back to the default, as in the Rust rule.
        $maxPostsPerPage = self::integer($values['max-posts-per-page'] ?? null) ?? self::DEFAULT_MAX_POSTS_PER_PAGE;

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
            maxPostsPerPage: $maxPostsPerPage < 0 ? self::DEFAULT_MAX_POSTS_PER_PAGE : $maxPostsPerPage,
            minCronInterval: self::integer($values['min-cron-interval'] ?? null) ?? self::DEFAULT_MIN_CRON_INTERVAL,
            additionalWordDelimiters: Shape::string($values['additional-word-delimiters'] ?? null) ?? '',
            honorPhpcsComments: ($values['honor-phpcs-comments'] ?? true) !== false,
        );
    }

    /**
     * Accepts an integer, or the numeric string a phpcs.xml property carries.
     */
    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) === 1 ? (int) $value : null;
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

        // A whitespace-only entry would otherwise match every name as a prefix.
        $strings = array_map(trim(...), array_filter($value, is_string(...)));

        return array_values(array_unique(array_filter($strings, static fn(string $item): bool => $item !== '')));
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
