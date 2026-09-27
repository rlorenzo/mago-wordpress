<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Rlorenzo\MagoWordPress\Settings;

use function count;
use function in_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;

/**
 * Reads the WPCS properties a `phpcs.xml` ruleset sets on its sniffs, in the shape
 * `Settings::fromArray()` accepts.
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
 */
final class PhpcsRuleset
{
    /**
     * WPCS sniff refs, mapped to the property names each one accepts. Scoping by ref
     * keeps an unrelated sniff (or a project's own custom one) from feeding the wrong
     * setting just because it happens to declare a same-named property.
     *
     * @var array<string, list<string>>
     */
    private const OWNED_PROPERTIES = [
        'WordPress.WP.I18n' => ['text_domain'],
        'WordPress.NamingConventions.PrefixAllGlobals' => ['prefixes'],
        'WordPress.Security.EscapeOutput' => ['customEscapingFunctions', 'customAutoEscapedFunctions'],
        'WordPress.Security.NonceVerification' => [
            'customSanitizingFunctions',
            'customUnslashingSanitizingFunctions',
        ],
        'WordPress.Security.ValidatedSanitizedInput' => [
            'customSanitizingFunctions',
            'customUnslashingSanitizingFunctions',
        ],
        'WordPress.WP.Capabilities' => ['custom_capabilities'],
        'WordPress.WP.PostsPerPage' => ['posts_per_page'],
        'WordPress.WP.CronInterval' => ['min_interval'],
        'WordPress.NamingConventions.ValidHookName' => ['additional_word_delimiters'],
    ];

    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function values(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $xml !== '' && $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $properties = self::properties($xpath);

        $values = [
            'text-domains' => $properties['text_domain'] ?? [],
            'prefixes' => $properties['prefixes'] ?? [],
            'minimum-wp-version' => self::minimumVersion($xpath),
            'max-posts-per-page' => self::last($properties['posts_per_page'] ?? []),
            'min-cron-interval' => self::last($properties['min_interval'] ?? []),
            'additional-word-delimiters' => self::last($properties['additional_word_delimiters'] ?? []),
        ];
        foreach (Settings::CUSTOM_LISTS as $option => $property) {
            $values[$option] = $properties[$property] ?? [];
        }

        return $values;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function properties(DOMXPath $xpath): array
    {
        $properties = [];
        foreach (self::elements($xpath, '//rule[@ref]') as $rule) {
            $owned = self::OWNED_PROPERTIES[$rule->getAttribute('ref')] ?? null;
            if ($owned === null) {
                continue;
            }

            foreach (self::elements($xpath, 'properties/property', $rule) as $property) {
                $name = $property->getAttribute('name');
                if (!in_array($name, $owned, strict: true)) {
                    continue;
                }

                $values = $property->getAttribute('type') === 'array'
                    ? self::elementValues($xpath, $property)
                    : [$property->getAttribute('value')];
                $properties[$name] = [...($properties[$name] ?? []), ...$values];
            }
        }

        return $properties;
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
        $configs = self::elements($xpath, '//config[@name="minimum_supported_wp_version"]');

        return $configs === [] ? null : $configs[count($configs) - 1]->getAttribute('value');
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
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
