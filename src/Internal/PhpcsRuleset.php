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
use function dirname;
use function explode;
use function file_get_contents;
use function in_array;
use function is_file;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function realpath;
use function str_starts_with;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Reads the WPCS properties a `phpcs.xml` ruleset sets on its sniffs, and the codes it
 * turns off (`exclude-patterns`), in the shape `Settings::fromArray()` accepts.
 *
 * Only properties set directly on one of the sniff refs below are read, and only the
 * property names that sniff actually accepts. A `<rule ref="SomeGroup">` that merely
 * *includes* one of these sniffs (a custom or third-party ruleset, or a WPCS group like
 * `WordPress-Extra`) is not followed, so properties set on it are not picked up; set them
 * directly on the sniff ref instead, as phpcs itself recommends. A `<rule ref>` that is a
 * path to a project ruleset file, absolute or relative to the including one, is merged in.
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
     * WPCS property => the composer.json `extra.mago-wordpress` key it feeds, and how a
     * phpcs.xml ruleset gives it: `list` takes every value, `last` the last one set,
     * `flag` the last one as a `true`/`false` string. `minimum_wp_version` (a config value)
     * and `exclude` (read per sniff) are handled on their own.
     *
     * @var array<string, array{non-empty-string, 'list'|'last'|'flag'}>
     */
    public const PROPERTY_SETTINGS = [
        'text_domain' => ['text-domains', 'list'],
        'prefixes' => ['prefixes', 'list'],
        'posts_per_page' => ['max-posts-per-page', 'last'],
        'min_interval' => ['min-cron-interval', 'last'],
        'additionalWordDelimiters' => ['additional-word-delimiters', 'last'],
        'treat_files_as_scoped' => ['treat-files-as-scoped', 'flag'],
        'strict_class_file_names' => ['strict-class-file-names', 'flag'],
        'is_theme' => ['is-theme', 'flag'],
        'customEscapingFunctions' => ['custom-escaping-functions', 'list'],
        'customAutoEscapedFunctions' => ['custom-auto-escaped-functions', 'list'],
        'customPrintingFunctions' => ['custom-printing-functions', 'list'],
        'customSanitizingFunctions' => ['custom-sanitizing-functions', 'list'],
        'customUnslashingSanitizingFunctions' => ['custom-unslashing-sanitizing-functions', 'list'],
        'customNonceVerificationFunctions' => ['custom-nonce-verification-functions', 'list'],
        'custom_capabilities' => ['custom-capabilities', 'list'],
        'allowed_custom_properties' => ['allowed-custom-properties', 'list'],
        'custom_test_classes' => ['custom-test-classes', 'list'],
        'customCacheGetFunctions' => ['custom-cache-get-functions', 'list'],
        'customCacheSetFunctions' => ['custom-cache-set-functions', 'list'],
        'customCacheDeleteFunctions' => ['custom-cache-delete-functions', 'list'],
        'customAllowedFunctionsList' => ['custom-allowed-functions-list', 'list'],
    ];

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
        'WordPress.Security.EscapeOutput' => [
            'customEscapingFunctions',
            'customAutoEscapedFunctions',
            'customPrintingFunctions',
        ],
        'WordPress.Security.NonceVerification' => [...self::SANITIZING_PROPERTIES, 'customNonceVerificationFunctions'],
        'WordPress.Security.ValidatedSanitizedInput' => self::SANITIZING_PROPERTIES,
        'WordPress.WP.Capabilities' => ['custom_capabilities'],
        'WordPress.DB.DirectDatabaseQuery' => [
            'customCacheGetFunctions',
            'customCacheSetFunctions',
            'customCacheDeleteFunctions',
        ],
        'WordPress.WP.CronInterval' => ['min_interval'],
        'WordPress.PHP.NoSilencedErrors' => ['customAllowedFunctionsList'],
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
        'WordPress.WP.AlternativeFunctions' => ['exclude'],
        'WordPress.WP.ClassNameCase' => ['exclude'],
        'WordPress.WP.DeprecatedClasses' => ['exclude'],
        'WordPress.WP.DeprecatedFunctions' => ['exclude'],
        'WordPress.WP.DiscouragedFunctions' => ['exclude'],
        'WordPress.WP.PostsPerPage' => ['posts_per_page', 'exclude'],
    ];

    /**
     * The sniffs that `SniffMap::RULES` maps, by the WPCS 3.4.1 standard that
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
        'Generic.CodeAnalysis.AssignmentInCondition',
        'Generic.CodeAnalysis.EmptyPHPStatement',
        'Generic.Files.ByteOrderMark',
        'Generic.Files.OneObjectStructurePerFile',
        'Generic.NamingConventions.UpperCaseConstantName',
        'Generic.PHP.BacktickOperator',
        'Generic.PHP.DisallowAlternativePHPTags',
        'Generic.PHP.DisallowShortOpenTag',
        'Generic.PHP.DiscourageGoto',
        'Generic.PHP.LowerCaseConstant',
        'Generic.PHP.LowerCaseKeyword',
        'Generic.PHP.LowerCaseType',
        'Generic.VersionControl.GitMergeConflict',
        'PEAR.NamingConventions.ValidClassName',
        'PSR2.Classes.PropertyDeclaration',
        'PSR2.Files.ClosingTag',
        'Squiz.PHP.DisallowMultipleAssignments',
        'Squiz.PHP.Eval',
        'Universal.Arrays.DisallowShortArraySyntax',
        'Universal.Operators.DisallowShortTernary',
        'Squiz.Scope.MethodScope',
        'Squiz.Classes.SelfMemberReference',
        'PSR2.Methods.MethodDeclaration',
    ];

    /** Message codes WordPress-Core sets to severity 0 and WordPress-Extra restores. */
    private const CORE_SILENCED_CODES = [
        'PSR2.Classes.PropertyDeclaration.Underscore',
        'Squiz.Classes.SelfMemberReference.NotUsed',
        'PSR2.Methods.MethodDeclaration.Underscore',
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
        'Generic.CodeAnalysis.ForLoopShouldBeWhileLoop',
        'Generic.CodeAnalysis.ForLoopWithTestFunctionCall',
        'Generic.CodeAnalysis.JumbledIncrementer',
        'Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence',
        'Generic.CodeAnalysis.UnconditionalIfStatement',
        'Generic.CodeAnalysis.UnnecessaryFinalModifier',
        'Generic.CodeAnalysis.UselessOverridingMethod',
        'Generic.PHP.ForbiddenFunctions',
        'Generic.Strings.UnnecessaryStringConcat',
        'Squiz.PHP.DisallowSizeFunctionsInLoops',
        'Universal.CodeAnalysis.ForeachUniqueAssignment',
        'Generic.CodeAnalysis.EmptyStatement',
        'Squiz.PHP.NonExecutableCode',
    ];

    /** phpcs hides reports below this severity by default. */
    private const DEFAULT_SEVERITY = 5;

    private function __construct() {}

    /**
     * Parses a ruleset, or returns NULL when it is empty or not well-formed XML. Given the
     * directory the ruleset lives in, a `<rule ref>` that names an existing ruleset file is
     * replaced by that file's `<rule>`, `<config>` and `<exclude-pattern>` elements, as
     * phpcs includes it. `$file` is the ruleset's own file name in that directory, so a chain
     * that leads back to it is cut instead of inlining it once more.
     */
    public static function load(string $xml, ?string $directory = null, ?string $file = null): ?DOMXPath
    {
        $document = self::parse($xml);
        if ($document === null) {
            return null;
        }

        if ($directory !== null) {
            $own = $file === null ? false : realpath($directory . DIRECTORY_SEPARATOR . $file);
            self::inline($document, $directory, $own === false ? [] : [$own]);
        }

        return new DOMXPath($document);
    }

    private static function parse(string $xml): ?DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $xml !== '' && $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * @param list<string> $seen the real paths of the rulesets being included, to stop a cycle
     */
    private static function inline(DOMDocument $document, string $directory, array $seen): void
    {
        foreach (self::elements(new DOMXPath($document), '/ruleset/rule[@ref]') as $rule) {
            $ref = $rule->getAttribute('ref');
            $path = str_starts_with($ref, '/') ? $ref : $directory . DIRECTORY_SEPARATOR . $ref;
            $real = is_file($path) ? realpath($path) : false;
            if ($real === false) {
                continue;
            }

            $included = in_array($real, $seen, strict: true) ? null : self::parse((string) file_get_contents($real));
            if ($included !== null) {
                self::inline($included, dirname($real), [...$seen, $real]);
                $children = self::elements(
                    new DOMXPath($included),
                    '/ruleset/*[self::rule or self::config or self::exclude-pattern]',
                );
                foreach ($children as $child) {
                    $copy = $document->importNode($child, true);
                    if ($copy !== false) {
                        $rule->parentNode?->insertBefore($copy, $rule);
                    }
                }
            }

            // The include itself is not a sniff ref; an unreadable or cyclic one is dropped.
            $rule->parentNode?->removeChild($rule);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function values(string $xml, ?string $directory = null, ?string $file = null): array
    {
        $xpath = self::load($xml, $directory, $file);
        if ($xpath === null) {
            return [];
        }

        $properties = self::properties($xpath);

        $values = [
            'minimum-wp-version' => self::minimumVersion($xpath),
            'exclude-groups' => self::excludeGroups($xpath),
        ];
        foreach (self::PROPERTY_SETTINGS as $property => [$key, $shape]) {
            $given = $properties[$property] ?? [];
            $values[$key] = match ($shape) {
                'list' => $given,
                'last' => self::last($given),
                'flag' => self::flag(self::last($given)),
            };
        }

        $values['exclude-patterns'] = self::excludePatterns($xpath);
        $values['levels'] = self::levels($xpath);

        return $values;
    }

    /**
     * The `levels` setting from a `<type>` on a whole sniff, for the extension rules that port
     * it (the last `<type>` wins). A `<type>` on one message code cannot re-level a rule.
     *
     * @return array<string, string>
     */
    private static function levels(DOMXPath $xpath): array
    {
        $levels = [];
        foreach (self::elements($xpath, '/ruleset/rule[@ref]/type') as $type) {
            $rule = $type->parentNode;
            $ref = $rule instanceof DOMElement ? $rule->getAttribute('ref') : '';
            foreach (SniffMap::RULES[$ref] ?? [] as $code) {
                if (SniffMap::isExtensionRule($code)) {
                    $levels[$code] = trim($type->textContent) === 'warning' ? 'warning' : 'error';
                }
            }
        }

        return $levels;
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

    /** A WPCS code, or a code of a non-WordPress sniff that `SniffMap::RULES` maps. */
    private static function isMapped(string $code): bool
    {
        if (str_starts_with($code, 'WordPress.')) {
            return true;
        }

        // The metrics and docs sniffs migrate() turns into core rule settings.
        foreach (array_keys(PhpcsMigration::CONFIGURED_SNIFFS) as $sniff) {
            if (str_starts_with($sniff . '.', $code . '.') || str_starts_with($code, $sniff . '.')) {
                return true;
            }
        }

        // A mapped sniff, one of its message codes, or a category or standard that holds one,
        // as phpcs applies a `<rule ref>`/`<exclude>` to every sniff under it.
        foreach (array_keys(SniffMap::RULES) as $sniff) {
            if (str_starts_with($sniff . '.', $code . '.') || str_starts_with($code, $sniff . '.')) {
                return true;
            }
        }

        return false;
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
            if (self::isMapped($name) && $exclude->getAttribute('phpcbf-only') !== 'true') {
                $patterns[$name] = ['*'];
            }
        }

        foreach (self::elements($xpath, '/ruleset/rule[@ref]') as $rule) {
            $ref = $rule->getAttribute('ref');
            if (!self::isMapped($ref) || $rule->getAttribute('phpcbf-only') === 'true') {
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
     * The broadest WPCS standard the ruleset names (`WordPress` > `WordPress-Extra` >
     * `WordPress-Core`), or NULL when it names none.
     */
    public static function standard(DOMXPath $xpath): ?string
    {
        $standards = self::standards($xpath);
        foreach (['WordPress', 'WordPress-Extra', 'WordPress-Core'] as $standard) {
            if (in_array($standard, $standards, strict: true)) {
                return $standard;
            }
        }

        return null;
    }

    /**
     * The mapped sniffs a WPCS standard leaves out, and the message codes it silences: none for
     * `WordPress` (or an unknown name), which runs every sniff.
     *
     * @return list<string>
     */
    public static function excludedSniffs(string $standard): array
    {
        $included = match ($standard) {
            'WordPress-Core' => self::CORE_SNIFFS,
            'WordPress-Extra' => [...self::CORE_SNIFFS, ...self::EXTRA_SNIFFS],
            default => null,
        };
        if ($included === null) {
            return [];
        }

        return [
            ...array_values(array_filter(
                array_keys(SniffMap::RULES),
                static fn(string $sniff): bool => !in_array($sniff, $included, strict: true),
            )),
            ...($standard === 'WordPress-Core' ? self::CORE_SILENCED_CODES : []),
        ];
    }

    /**
     * Mapped sniffs outside every WPCS standard the ruleset names, unless a `<rule ref>`
     * names the sniff, its category, or one of its message codes.
     *
     * @return list<string>
     */
    public static function excludedByStandard(DOMXPath $xpath): array
    {
        $standard = self::standard($xpath);
        if ($standard === null) {
            return [];
        }

        $refs = [];
        foreach (self::elements($xpath, '/ruleset/rule[@ref]') as $rule) {
            $refs[] = $rule->getAttribute('ref');
        }

        $excluded = [];
        foreach (self::excludedSniffs($standard) as $sniff) {
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

                $groups[$sniff] = self::arrayValues($xpath, $property, $groups[$sniff] ?? []);
            }
        }

        return array_filter(array_map(array_values(...), $groups), static fn(array $values): bool => $values !== []);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function properties(DOMXPath $xpath): array
    {
        $properties = [];
        $arrays = [];
        foreach (self::elements($xpath, '//rule[@ref]') as $rule) {
            $ref = $rule->getAttribute('ref');
            foreach (self::ownedProperties($xpath, $rule) as $property) {
                $name = $property->getAttribute('name');
                if ($property->getAttribute('type') === 'array') {
                    // Keys survive until the last assignment, so a later `extend` can replace by key.
                    $arrays[$name][$ref] = self::arrayValues($xpath, $property, $arrays[$name][$ref] ?? []);
                } else {
                    $properties[$name] = [...($properties[$name] ?? []), $property->getAttribute('value')];
                }
            }
        }

        // Each sniff keeps its own array; the one setting is the union across the sniffs.
        foreach ($arrays as $name => $perSniff) {
            $properties[$name] = array_values(array_unique(array_merge(...array_values(array_map(
                array_values(...),
                $perSniff,
            )))));
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
     * An array property as phpcs reads it: `<element [key] value>` children, else the
     * deprecated comma-separated `value` with optional `key=>value` pairs. A later
     * assignment replaces the array unless it says `extend="true"`. Keyed entries keep
     * their key, so a map is not a list (`array_is_list()`).
     *
     * @param array<array-key, string> $previous
     * @return array<array-key, string>
     */
    public static function arrayValues(DOMXPath $xpath, DOMElement $property, array $previous = []): array
    {
        $values = $property->getAttribute('extend') === 'true' ? $previous : [];
        $elements = self::elements($xpath, 'element', $property);
        if ($elements !== []) {
            foreach ($elements as $element) {
                if ($element->getAttribute('phpcbf-only') === 'true') {
                    continue;
                }

                $key = $element->getAttribute('key');
                if (trim($key) !== '') {
                    $values[$key] = $element->getAttribute('value');
                } else {
                    $values[] = $element->getAttribute('value');
                }
            }

            return $values;
        }

        $value = $property->getAttribute('value');
        foreach ($value === '' ? [] : explode(',', $value) as $item) {
            [$key, $mapped] = explode('=>', $item . '=>');
            if ($mapped !== '') {
                $values[trim($key)] = trim($mapped);
            } else {
                $values[] = trim($key);
            }
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
