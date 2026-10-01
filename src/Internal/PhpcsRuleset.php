<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Rlorenzo\MagoWordPress\Settings;

use function array_filter;
use function array_keys;
use function array_values;
use function count;
use function explode;
use function in_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function str_starts_with;
use function trim;

/**
 * Reads the WPCS properties a `phpcs.xml` ruleset sets on its sniffs, and the codes it
 * turns off (`exclude-patterns`), in the shape `Settings::fromArray()` accepts.
 *
 * Only properties set directly on one of the sniff refs below are read, and only the
 * property names that sniff actually accepts. A `<rule ref="SomeGroup">` that merely
 * *includes* one of these sniffs (a custom or third-party ruleset, or a WPCS group like
 * `WordPress-Extra`) is not followed, so properties set on it are not picked up; set them
 * directly on the sniff ref instead, as phpcs itself recommends.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PhpcsRuleset
{
    private const SANITIZING_PROPERTIES = ['customSanitizingFunctions', 'customUnslashingSanitizingFunctions'];

    /**
     * WPCS sniff refs, mapped to the property names each one accepts. Scoping by ref
     * keeps an unrelated sniff (or a project's own custom one) from feeding the wrong
     * setting just because it happens to declare a same-named property.
     *
     * @var array<string, list<string>>
     */
    public const OWNED_PROPERTIES = [
        'WordPress.WP.I18n' => ['text_domain'],
        'WordPress.NamingConventions.PrefixAllGlobals' => ['prefixes', 'custom_test_classes'],
        'WordPress.Security.EscapeOutput' => ['customEscapingFunctions', 'customAutoEscapedFunctions'],
        'WordPress.Security.NonceVerification' => self::SANITIZING_PROPERTIES,
        'WordPress.Security.ValidatedSanitizedInput' => self::SANITIZING_PROPERTIES,
        'WordPress.WP.Capabilities' => ['custom_capabilities'],
        'WordPress.WP.CronInterval' => ['min_interval'],
        'WordPress.NamingConventions.ValidHookName' => ['additionalWordDelimiters'],
        'WordPress.NamingConventions.ValidVariableName' => ['allowed_custom_properties'],
        'WordPress.Files.FileName' => ['strict_class_file_names', 'is_theme', 'custom_test_classes'],
        'WordPress.WP.GlobalVariablesOverride' => ['treat_files_as_scoped', 'custom_test_classes'],
        // The restriction sniffs this package ports; each `exclude` is read per sniff (excludeGroups()).
        'WordPress.DateTime.RestrictedFunctions' => ['exclude'],
        'WordPress.DB.RestrictedClasses' => ['exclude'],
        'WordPress.DB.RestrictedFunctions' => ['exclude'],
        'WordPress.DB.SlowDBQuery' => ['exclude'],
        'WordPress.PHP.DevelopmentFunctions' => ['exclude'],
        'WordPress.PHP.DiscouragedPHPFunctions' => ['exclude'],
        'WordPress.PHP.DontExtract' => ['exclude'],
        'WordPress.PHP.RestrictedPHPFunctions' => ['exclude'],
        'WordPress.Security.SafeRedirect' => ['exclude'],
        'WordPress.WP.ClassNameCase' => ['exclude'],
        'WordPress.WP.DeprecatedClasses' => ['exclude'],
        'WordPress.WP.DeprecatedFunctions' => ['exclude'],
        'WordPress.WP.DiscouragedFunctions' => ['exclude'],
        'WordPress.WP.PostsPerPage' => ['posts_per_page', 'exclude'],
    ];

    /**
     * The WPCS sniffs that `Report::SNIFF_RULES` maps, by the WPCS 3.4.1 standard that
     * pulls them in. `WordPress-Extra` includes `WordPress-Core`; `WordPress` includes
     * every sniff, including the three neither group lists (DirectDatabaseQuery,
     * SlowDBQuery, ValidatedSanitizedInput).
     */
    private const CORE_SNIFFS = [
        'WordPress.CodeAnalysis.AssignmentInTernaryCondition',
        'WordPress.DateTime.CurrentTimeTimestamp',
        'WordPress.DateTime.RestrictedFunctions',
        'WordPress.DB.PreparedSQL',
        'WordPress.DB.PreparedSQLPlaceholders',
        'WordPress.DB.RestrictedClasses',
        'WordPress.DB.RestrictedFunctions',
        'WordPress.Files.FileName',
        'WordPress.NamingConventions.ValidFunctionName',
        'WordPress.NamingConventions.ValidHookName',
        'WordPress.NamingConventions.ValidVariableName',
        'WordPress.PHP.DontExtract',
        'WordPress.PHP.NoSilencedErrors',
        'WordPress.PHP.RestrictedPHPFunctions',
        'WordPress.PHP.StrictInArray',
        'WordPress.PHP.TypeCasts',
        'WordPress.PHP.YodaConditions',
        'WordPress.WP.CapitalPDangit',
        'WordPress.WP.ClassNameCase',
        'WordPress.WP.I18n',
    ];

    private const EXTRA_SNIFFS = [
        'WordPress.CodeAnalysis.EscapedNotTranslated',
        'WordPress.NamingConventions.PrefixAllGlobals',
        'WordPress.NamingConventions.ValidPostTypeSlug',
        'WordPress.PHP.DevelopmentFunctions',
        'WordPress.PHP.DiscouragedPHPFunctions',
        'WordPress.PHP.IniSet',
        'WordPress.PHP.PregQuoteDelimiter',
        'WordPress.Security.EscapeOutput',
        'WordPress.Security.NonceVerification',
        'WordPress.Security.PluginMenuSlug',
        'WordPress.Security.SafeRedirect',
        'WordPress.WP.AlternativeFunctions',
        'WordPress.WP.Capabilities',
        'WordPress.WP.CronInterval',
        'WordPress.WP.DeprecatedClasses',
        'WordPress.WP.DeprecatedFunctions',
        'WordPress.WP.DeprecatedParameters',
        'WordPress.WP.DeprecatedParameterValues',
        'WordPress.WP.DiscouragedConstants',
        'WordPress.WP.DiscouragedFunctions',
        'WordPress.WP.EnqueuedResourceParameters',
        'WordPress.WP.EnqueuedResources',
        'WordPress.WP.GetMetaSingle',
        'WordPress.WP.GlobalVariablesOverride',
        'WordPress.WP.PostsPerPage',
    ];

    /** phpcs hides reports below this severity by default. */
    private const DEFAULT_SEVERITY = 5;

    private function __construct() {}

    /**
     * Parses a ruleset, or returns NULL when it is empty or not well-formed XML.
     */
    public static function load(string $xml): ?DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $xml !== '' && $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($document) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function values(string $xml): array
    {
        $xpath = self::load($xml);
        if ($xpath === null) {
            return [];
        }

        $properties = self::properties($xpath);

        $values = [
            'text-domains' => $properties['text_domain'] ?? [],
            'prefixes' => $properties['prefixes'] ?? [],
            'minimum-wp-version' => self::minimumVersion($xpath),
            'max-posts-per-page' => self::last($properties['posts_per_page'] ?? []),
            'min-cron-interval' => self::last($properties['min_interval'] ?? []),
            'additional-word-delimiters' => self::last($properties['additionalWordDelimiters'] ?? []),
            'treat-files-as-scoped' => self::flag(self::last($properties['treat_files_as_scoped'] ?? [])),
            'strict-class-file-names' => self::flag(self::last($properties['strict_class_file_names'] ?? [])),
            'is-theme' => self::flag(self::last($properties['is_theme'] ?? [])),
            'exclude-groups' => self::excludeGroups($xpath),
        ];
        foreach (Settings::CUSTOM_LISTS as $option => $property) {
            $values[$option] = $properties[$property] ?? [];
        }

        $values['exclude-patterns'] = self::excludePatterns($xpath);

        return $values;
    }

    /**
     * The WPCS standards the ruleset pulls in by name, or none when it only includes
     * custom or third-party standards (which may include WPCS in turn).
     *
     * @return list<string>
     */
    public static function standards(DOMXPath $xpath): array
    {
        $standards = [];
        foreach (self::elements($xpath, '/ruleset/rule[@ref]') as $rule) {
            $ref = $rule->getAttribute('ref');
            if (in_array($ref, ['WordPress', 'WordPress-Extra', 'WordPress-Core'], strict: true)) {
                $standards[] = $ref;
            }
        }

        return $standards;
    }

    /**
     * WPCS codes the ruleset turns off, everywhere (`*`) or for some paths, as phpcs
     * `<exclude-pattern>` values: mapped sniffs its standards leave out, `<exclude name>`,
     * a `<severity>` below phpcs's default of 5, and `<exclude-pattern>` inside a
     * `<rule ref>`. `type="relative"` patterns and phpcbf-only entries are skipped.
     *
     * @return array<string, list<string>>
     */
    public static function excludePatterns(DOMXPath $xpath): array
    {
        $patterns = [];
        foreach (self::excludedByStandard($xpath) as $sniff) {
            $patterns[$sniff] = ['*'];
        }

        foreach (self::elements($xpath, '/ruleset/rule[@ref]/exclude[@name]') as $exclude) {
            $name = $exclude->getAttribute('name');
            if (str_starts_with($name, 'WordPress.') && $exclude->getAttribute('phpcbf-only') !== 'true') {
                $patterns[$name] = ['*'];
            }
        }

        foreach (self::elements($xpath, '/ruleset/rule[@ref]') as $rule) {
            $ref = $rule->getAttribute('ref');
            if (!str_starts_with($ref, 'WordPress.') || $rule->getAttribute('phpcbf-only') === 'true') {
                continue;
            }

            if (self::isSilenced($xpath, $rule)) {
                $patterns[$ref] = ['*'];
                continue;
            }

            foreach (self::elements($xpath, 'exclude-pattern', $rule) as $pattern) {
                if ($pattern->getAttribute('type') === 'relative' || $pattern->getAttribute('phpcbf-only') === 'true') {
                    continue;
                }

                $value = trim($pattern->textContent);
                if ($value !== '' && ($patterns[$ref] ?? []) !== ['*']) {
                    $patterns[$ref][] = $value;
                }
            }
        }

        return $patterns;
    }

    /**
     * A `<severity>` below phpcs's default threshold hides the code's reports.
     */
    public static function isSilenced(DOMXPath $xpath, DOMElement $rule): bool
    {
        $severity = self::elements($xpath, 'severity', $rule);

        return $severity !== [] && (int) trim($severity[count($severity) - 1]->textContent) < self::DEFAULT_SEVERITY;
    }

    /**
     * Mapped sniffs outside every WPCS standard the ruleset names, unless a `<rule ref>`
     * names the sniff, its category, or one of its message codes.
     *
     * @return list<string>
     */
    private static function excludedByStandard(DOMXPath $xpath): array
    {
        $standards = self::standards($xpath);
        if ($standards === [] || in_array('WordPress', $standards, strict: true)) {
            return [];
        }

        $included = in_array('WordPress-Extra', $standards, strict: true)
            ? [...self::CORE_SNIFFS, ...self::EXTRA_SNIFFS]
            : self::CORE_SNIFFS;
        $refs = [];
        foreach (self::elements($xpath, '/ruleset/rule[@ref]') as $rule) {
            $refs[] = $rule->getAttribute('ref');
        }

        $excluded = [];
        foreach (array_keys(Report::SNIFF_RULES) as $sniff) {
            if (in_array($sniff, $included, strict: true)) {
                continue;
            }

            foreach ($refs as $ref) {
                // ponytail: a message-code ref re-includes its whole sniff; phpcs includes only that code.
                if (str_starts_with($sniff . '.', $ref . '.') || str_starts_with($ref, $sniff . '.')) {
                    continue 2;
                }
            }

            $excluded[] = $sniff;
        }

        return $excluded;
    }

    /**
     * Each restriction sniff's `exclude` property: the function groups it drops. Read per
     * sniff, since every one of them has its own `exclude`.
     *
     * @return array<string, list<string>>
     */
    private static function excludeGroups(DOMXPath $xpath): array
    {
        $groups = [];
        foreach (self::elements($xpath, '//rule[@ref]') as $rule) {
            $sniff = $rule->getAttribute('ref');
            foreach (self::ownedProperties($xpath, $rule) as $property) {
                if ($property->getAttribute('name') !== 'exclude') {
                    continue;
                }

                // An array property can also be given as a comma-separated `value`.
                $inline = explode(',', $property->getAttribute('value'));
                // As in phpcs, a later assignment replaces the array unless it says extend="true".
                $values = $property->getAttribute('extend') === 'true' ? $groups[$sniff] ?? [] : [];
                foreach ([...self::elementValues($xpath, $property), ...$inline] as $group) {
                    $group = trim($group);
                    if ($group !== '') {
                        $values[] = $group;
                    }
                }

                $groups[$sniff] = $values;
            }
        }

        return array_filter($groups, static fn(array $values): bool => $values !== []);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function properties(DOMXPath $xpath): array
    {
        $properties = [];
        foreach (self::elements($xpath, '//rule[@ref]') as $rule) {
            foreach (self::ownedProperties($xpath, $rule) as $property) {
                $name = $property->getAttribute('name');
                $values = $property->getAttribute('type') === 'array'
                    ? self::elementValues($xpath, $property)
                    : [$property->getAttribute('value')];
                $properties[$name] = [...($properties[$name] ?? []), ...$values];
            }
        }

        return $properties;
    }

    /**
     * The `<property>` elements of a sniff ref that the sniff actually accepts.
     *
     * @return list<DOMElement>
     */
    private static function ownedProperties(DOMXPath $xpath, DOMElement $rule): array
    {
        $owned = self::OWNED_PROPERTIES[$rule->getAttribute('ref')] ?? [];
        if ($owned === []) {
            return [];
        }

        return array_values(array_filter(
            self::elements($xpath, 'properties/property', $rule),
            static fn(DOMElement $property): bool => in_array($property->getAttribute('name'), $owned, strict: true),
        ));
    }

    /**
     * A scalar property set more than once keeps its last value, as phpcs does.
     *
     * @param list<string> $values
     */
    private static function last(array $values): ?string
    {
        return $values === [] ? null : $values[count($values) - 1];
    }

    /**
     * phpcs turns a `true`/`false` property value into a boolean.
     */
    private static function flag(?string $value): ?bool
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private static function elementValues(DOMXPath $xpath, DOMElement $property): array
    {
        $values = [];
        foreach (self::elements($xpath, 'element', $property) as $element) {
            $values[] = $element->getAttribute('value');
        }

        return $values;
    }

    private static function minimumVersion(DOMXPath $xpath): ?string
    {
        // WPCS 3.0 renamed the config to minimum_wp_version; the old name is still read.
        $configs = self::elements(
            $xpath,
            '//config[@name="minimum_wp_version" or @name="minimum_supported_wp_version"]',
        );

        return $configs === [] ? null : $configs[count($configs) - 1]->getAttribute('value');
    }

    /**
     * @return list<DOMElement>
     */
    public static function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        return self::elementsIn($xpath->query($query, $context));
    }

    /**
     * `DOMXPath::query()` returns false for a malformed expression.
     *
     * @return list<DOMElement>
     */
    private static function elementsIn(mixed $nodes): array
    {
        if (!$nodes instanceof DOMNodeList) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $elements[] = $node;
        }

        return $elements;
    }
}
