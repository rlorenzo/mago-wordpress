<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Settings;

final class SettingsTest extends TestCase
{
    public function testFromArrayLowercasesAndDefaults(): void
    {
        $settings = Settings::fromArray(['text-domains' => 'My-Plugin', 'prefixes' => ['MP', 'mp_']]);

        self::assertSame(['My-Plugin'], $settings->textDomains);
        self::assertSame(['mp', 'mp_'], $settings->prefixes);
        self::assertSame('6.0', $settings->minimumWpVersion);
        self::assertSame([], $settings->customList('custom-escaping-functions'));
    }

    public function testListsAreDeduplicatedAndCapabilitiesKeepCase(): void
    {
        $settings = Settings::fromArray([
            'prefixes' => ['MP', 'mp'],
            'custom-capabilities' => ['Edit_Things', 'Edit_Things'],
        ]);

        self::assertSame(['mp'], $settings->prefixes);
        self::assertSame(['Edit_Things'], $settings->customList('custom-capabilities'));
    }

    public function testListEntriesAreTrimmedAndBlankOnesDropped(): void
    {
        $settings = Settings::fromArray(['prefixes' => [' mp ', '   ', ''], 'text-domains' => [' my-plugin']]);

        self::assertSame(['mp'], $settings->prefixes);
        self::assertSame(['my-plugin'], $settings->textDomains);
    }

    public function testPhpcsRulesetPropertiesAreRead(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="Example">
              <config name="minimum_supported_wp_version" value="6.8"/>
              <rule ref="WordPress.WP.I18n">
                <properties>
                  <property name="text_domain" type="array"><element value="example"/></property>
                </properties>
              </rule>
              <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
                <properties>
                  <property name="prefixes" type="array"><element value="ex"/><element value="example"/></property>
                </properties>
              </rule>
              <rule ref="WordPress.Security.EscapeOutput">
                <properties>
                  <property name="customEscapingFunctions" type="array"><element value="ex_esc"/></property>
                </properties>
              </rule>
            </ruleset>
            XML;

        $settings = Settings::fromArray(PhpcsRuleset::values($xml));

        self::assertSame(['example'], $settings->textDomains);
        self::assertSame(['ex', 'example'], $settings->prefixes);
        self::assertSame('6.8', $settings->minimumWpVersion);
        self::assertSame(['ex_esc'], $settings->customList('custom-escaping-functions'));
    }

    public function testInvalidXmlYieldsDefaults(): void
    {
        self::assertSame([], PhpcsRuleset::values('not xml'));
    }
}
