<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Internal\Shape;

use function array_fill_keys;
use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_encode;
use function ltrim;
use function preg_match;
use function strcasecmp;
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
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Settings
{
    private const DEFAULT_MINIMUM_WP_VERSION = '6.7';

    private const DEFAULT_MAX_POSTS_PER_PAGE = 100;

    private const DEFAULT_MIN_CRON_INTERVAL = 900;

    /** The WPCS standard whose sniffs run; `WordPress-Core` and `WordPress-Extra` leave some out. */
    public const DEFAULT_STANDARD = 'WordPress';

    /**
     * Keys accepted in composer.json `extra.mago-wordpress` for the custom lists,
     * mapped to the WPCS property they mirror.
     */
    public const CUSTOM_LISTS = [
        'custom-escaping-functions' => 'customEscapingFunctions',
        'custom-auto-escaped-functions' => 'customAutoEscapedFunctions',
        'custom-printing-functions' => 'customPrintingFunctions',
        'custom-sanitizing-functions' => 'customSanitizingFunctions',
        'custom-unslashing-sanitizing-functions' => 'customUnslashingSanitizingFunctions',
        'custom-nonce-verification-functions' => 'customNonceVerificationFunctions',
        'custom-capabilities' => 'custom_capabilities',
        'allowed-custom-properties' => 'allowed_custom_properties',
        'custom-test-classes' => 'custom_test_classes',
        'custom-cache-get-functions' => 'customCacheGetFunctions',
        'custom-cache-set-functions' => 'customCacheSetFunctions',
        'custom-cache-delete-functions' => 'customCacheDeleteFunctions',
        'custom-allowed-functions-list' => 'customAllowedFunctionsList',
    ];

    /** The standards `standard` accepts; phpcs finds them case-insensitively on macOS and Windows. */
    public const STANDARDS = ['WordPress', 'WordPress-Core', 'WordPress-Extra'];

    /** Every key `fromArray()` reads besides the custom lists, by the type it expects. */
    private const KEYS = [
        'text-domains' => 'list',
        'prefixes' => 'list',
        'minimum-wp-version' => 'string',
        'additional-word-delimiters' => 'string',
        'max-posts-per-page' => 'integer',
        'min-cron-interval' => 'integer',
        'honor-phpcs-comments' => 'boolean',
        'treat-files-as-scoped' => 'boolean',
        'strict-class-file-names' => 'boolean',
        'is-theme' => 'boolean',
        'exclude-patterns' => 'map',
        'exclude-groups' => 'map',
        'levels' => 'levels',
        'standard' => 'standard',
    ];

    private const EXPECTED = [
        'list' => 'a string or a list of strings',
        'string' => 'a string',
        'integer' => 'an integer',
        'boolean' => 'true or false',
        'map' => 'an object whose values are a string or a list of strings',
        'levels' => 'an object of rule code => level, such as {"wordpress/capital-p-dangit": "error"}',
        'standard' => 'WordPress, WordPress-Core or WordPress-Extra',
    ];

    /**
     * Custom lists whose entries are not lowercased like function names: `exact` keeps the
     * case (capability and property names are case-sensitive), `class` lowercases and drops
     * a leading `\` (WPCS takes test class names as FQNs without one, and tolerates it).
     */
    private const LIST_NORMALIZATION = [
        'custom-capabilities' => 'exact',
        'allowed-custom-properties' => 'exact',
        'custom-test-classes' => 'class',
    ];

    /**
     * @param list<string> $textDomains
     * @param list<string> $prefixes
     * @param array<string, list<string>> $customLists keyed by composer.json option name
     * @param array<string, list<string>> $excludePatterns WPCS code => phpcs `<exclude-pattern>` values
     * @param array<string, list<string>> $excludeGroups WPCS sniff => names of its function groups to skip
     * @param array<array-key, mixed> $levels rule code => `error`, `warning`, `note` or `help`, as
     *        given; `WordPressExtension` validates both
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
        public readonly array $excludePatterns = [],
        public readonly array $excludeGroups = [],
        public readonly bool $treatFilesAsScoped = false,
        public readonly bool $strictClassFileNames = true,
        public readonly bool $isTheme = false,
        public readonly string $standard = self::DEFAULT_STANDARD,
        public readonly array $levels = [],
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
        // A negative maximum is invalid and falls back to the default.
        $maxPostsPerPage = self::integer($values['max-posts-per-page'] ?? null) ?? self::DEFAULT_MAX_POSTS_PER_PAGE;

        $customLists = [];
        foreach (array_keys(self::CUSTOM_LISTS) as $option) {
            $list = self::stringList($values[$option] ?? []);
            $customLists[$option] = match (self::LIST_NORMALIZATION[$option] ?? 'lower') {
                'exact' => $list,
                'lower' => self::lowercased($list),
                default => self::unique(array_map(static fn(string $class): string => ltrim(
                    $class,
                    characters: '\\',
                ), self::lowercased($list))),
            };
        }

        // The sniffs the standard leaves out are excluded everywhere, like a phpcs.xml built on it;
        // any other name (`WordPress` included) runs every sniff.
        $standard = self::standard($values['standard'] ?? null) ?? self::DEFAULT_STANDARD;
        $excludePatterns = [
            ...self::stringListMap($values['exclude-patterns'] ?? []),
            ...array_fill_keys(PhpcsRuleset::excludedSniffs($standard), ['*']),
        ];

        return new self(
            textDomains: self::unique(self::stringList($values['text-domains'] ?? [])),
            prefixes: self::lowercased(self::stringList($values['prefixes'] ?? [])),
            minimumWpVersion: Shape::string($values['minimum-wp-version'] ?? null) ?? self::DEFAULT_MINIMUM_WP_VERSION,
            customLists: $customLists,
            maxPostsPerPage: $maxPostsPerPage < 0 ? self::DEFAULT_MAX_POSTS_PER_PAGE : $maxPostsPerPage,
            minCronInterval: self::integer($values['min-cron-interval'] ?? null) ?? self::DEFAULT_MIN_CRON_INTERVAL,
            additionalWordDelimiters: Shape::string($values['additional-word-delimiters'] ?? null) ?? '',
            honorPhpcsComments: ($values['honor-phpcs-comments'] ?? true) !== false,
            excludePatterns: $excludePatterns,
            excludeGroups: self::stringListMap($values['exclude-groups'] ?? []),
            treatFilesAsScoped: self::boolean($values['treat-files-as-scoped'] ?? null) ?? false,
            strictClassFileNames: self::boolean($values['strict-class-file-names'] ?? null) ?? true,
            isTheme: self::boolean($values['is-theme'] ?? null) ?? false,
            standard: $standard,
            levels: Shape::arrayAt($values, 'levels') ?? [],
        );
    }

    /**
     * What is wrong with the settings, one line per key naming it and what it expects;
     * `fromArray()` would ignore these values or fall back to a default. NULL means unset.
     *
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    public static function problems(array $values): array
    {
        $problems = [];
        foreach ($values as $key => $value) {
            $type = self::KEYS[$key] ?? (array_key_exists($key, self::CUSTOM_LISTS) ? 'list' : null);
            if ($type === null) {
                $problems[] = "unknown setting `{$key}`.";
            } elseif ($value !== null && !self::isValid($type, $value)) {
                $problems[] =
                    "`{$key}` must be " . self::EXPECTED[$type] . ', not ' . (string) json_encode($value) . '.';
            }
        }

        return $problems;
    }

    private static function isValid(string $type, mixed $value): bool
    {
        return match ($type) {
            'list' => is_string($value) || self::isStringList($value),
            'string' => is_string($value),
            'integer' => self::integer($value) !== null,
            'boolean' => self::boolean($value) !== null,
            'map' => is_array($value)
                && ($value === [] || !array_is_list($value))
                && array_filter($value, static fn(mixed $item): bool => !is_string($item) && !self::isStringList($item))
                    === [],
            'levels' => is_array($value)
                && ($value === [] || !array_is_list($value))
                && array_filter($value, static fn(mixed $item): bool => !is_string($item)) === [],
            default => self::standard($value) !== null,
        };
    }

    private static function isStringList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, is_string(...)) === $value;
    }

    /** One of STANDARDS, matched regardless of case, in its own spelling. */
    private static function standard(mixed $value): ?string
    {
        foreach (self::STANDARDS as $standard) {
            if (is_string($value) && strcasecmp($value, $standard) === 0) {
                return $standard;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function stringListMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $patterns = [];
        foreach (array_keys($value) as $code) {
            $list = self::stringList($value[$code]);
            if (!is_string($code) || $code === '' || $list === []) {
                continue;
            }

            $patterns[$code] = $list;
        }

        return $patterns;
    }

    /**
     * Accepts a boolean, or the `true`/`false` string a phpcs.xml property carries.
     */
    private static function boolean(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 'true' => true,
            $value === 'false' => false,
            default => null,
        };
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
