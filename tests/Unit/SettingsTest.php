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
        self::assertSame('6.7', $settings->minimumWpVersion);
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
              <config name="minimum_wp_version" value="6.8"/>
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

    public function testThresholdSettingsDefaultAndParse(): void
    {
        $defaults = Settings::fromArray([]);
        self::assertSame(100, $defaults->maxPostsPerPage);
        self::assertSame(900, $defaults->minCronInterval);
        self::assertSame('', $defaults->additionalWordDelimiters);

        $settings = Settings::fromArray([
            'max-posts-per-page' => '250',
            'min-cron-interval' => 60,
            'additional-word-delimiters' => '/.',
        ]);
        self::assertSame(250, $settings->maxPostsPerPage);
        self::assertSame(60, $settings->minCronInterval);
        self::assertSame('/.', $settings->additionalWordDelimiters);

        self::assertSame(100, Settings::fromArray(['max-posts-per-page' => -5])->maxPostsPerPage);
        self::assertSame(900, Settings::fromArray(['min-cron-interval' => 'often'])->minCronInterval);
    }

    public function testPhpcsThresholdPropertiesAreRead(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="Example">
              <rule ref="WordPress.WP.PostsPerPage">
                <properties><property name="posts_per_page" value="50"/></properties>
              </rule>
              <rule ref="WordPress.WP.CronInterval">
                <properties><property name="min_interval" value="600"/></properties>
              </rule>
              <rule ref="WordPress.NamingConventions.ValidHookName">
                <properties><property name="additionalWordDelimiters" value="-/"/></properties>
              </rule>
            </ruleset>
            XML;

        $settings = Settings::fromArray(PhpcsRuleset::values($xml));

        self::assertSame(50, $settings->maxPostsPerPage);
        self::assertSame(600, $settings->minCronInterval);
        self::assertSame('-/', $settings->additionalWordDelimiters);
    }

    public function testInvalidXmlYieldsDefaults(): void
    {
        self::assertSame([], PhpcsRuleset::values('not xml'));
    }

    public function testPhpcsPropertiesAreScopedToTheirOwningRule(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="Example">
              <rule ref="Some.Unrelated.Sniff">
                <properties>
                  <property name="text_domain" type="array"><element value="wrong"/></property>
                  <property name="prefixes" type="array"><element value="wrong"/></property>
                </properties>
              </rule>
              <rule ref="WordPress.WP.I18n">
                <properties>
                  <property name="text_domain" type="array"><element value="right"/></property>
                </properties>
              </rule>
            </ruleset>
            XML;

        $settings = Settings::fromArray(PhpcsRuleset::values($xml));

        self::assertSame(['right'], $settings->textDomains);
        self::assertSame([], $settings->prefixes);
    }

    public function testPhpcsPropertiesDoNotFollowIncludedRulesets(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="Example">
              <rule ref="WordPress-Extra">
                <properties>
                  <property name="text_domain" type="array"><element value="ignored"/></property>
                </properties>
              </rule>
            </ruleset>
            XML;

        self::assertSame([], Settings::fromArray(PhpcsRuleset::values($xml))->textDomains);
    }

    public function testHonorPhpcsCommentsDefaultsOnAndOnlyFalseTurnsItOff(): void
    {
        self::assertTrue(Settings::fromArray([])->honorPhpcsComments);
        self::assertTrue(Settings::fromArray(['honor-phpcs-comments' => 'no'])->honorPhpcsComments);
        self::assertFalse(Settings::fromArray(['honor-phpcs-comments' => false])->honorPhpcsComments);
    }
}
