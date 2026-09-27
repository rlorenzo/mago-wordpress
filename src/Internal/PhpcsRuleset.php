<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Rlorenzo\MagoWordPress\Settings;

use function libxml_use_internal_errors;

/**
 * Reads the WPCS properties a `phpcs.xml` ruleset sets on its sniffs, in the shape
 * `Settings::fromArray()` accepts.
 *
 * @internal
 */
final class PhpcsRuleset
{
    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function values(string $xml): array
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        if ($xml === '' || !$document->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $properties = self::properties($xpath);

        $values = [
            'text-domains' => $properties['text_domain'] ?? [],
            'prefixes' => $properties['prefixes'] ?? [],
            'minimum-wp-version' => self::minimumVersion($xpath),
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
        foreach (self::elements($xpath, '//property') as $property) {
            $name = $property->getAttribute('name');
            $values = $property->getAttribute('type') === 'array'
                ? self::elementValues($xpath, $property)
                : [$property->getAttribute('value')];
            $properties[$name] = [...($properties[$name] ?? []), ...$values];
        }

        return $properties;
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

    private static function minimumVersion(DOMXPath $xpath): string
    {
        $version = '6.0';
        foreach (self::elements($xpath, '//config[@name="minimum_supported_wp_version"]') as $config) {
            $version = $config->getAttribute('value');
        }

        return $version;
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $elements = [];
        $nodes = $xpath->query($query, $context);
        if (!$nodes instanceof DOMNodeList) {
            return [];
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
