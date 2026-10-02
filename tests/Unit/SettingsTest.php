<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Settings;

/**
 * @mago-expect lint:too-many-methods
 */
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

        // `CUSTOM_LISTS` is `@api`: option => the WPCS property, as released in 1.1.0.
        foreach (Settings::CUSTOM_LISTS as $option => $property) {
            self::assertSame([$option, 'list'], PhpcsRuleset::PROPERTY_SETTINGS[$property] ?? null);
        }
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

    public function testStandardExcludesTheSniffsItLeavesOut(): void
    {
        $full = Settings::fromArray(['exclude-patterns' => ['WordPress.WP.I18n' => ['*/legacy/*']]]);
        self::assertSame('WordPress', $full->standard);
        self::assertSame(['WordPress.WP.I18n' => ['*/legacy/*']], $full->excludePatterns);

        $extra = Settings::fromArray([
            'standard' => 'WordPress-Extra',
            'exclude-patterns' => ['WordPress.WP.I18n' => ['*/legacy/*']],
        ]);
        self::assertSame('WordPress-Extra', $extra->standard);
        self::assertSame(
            [
                'WordPress.WP.I18n' => ['*/legacy/*'],
                'WordPress.DB.DirectDatabaseQuery' => ['*'],
                'WordPress.DB.SlowDBQuery' => ['*'],
                'WordPress.Security.ValidatedSanitizedInput' => ['*'],
            ],
            $extra->excludePatterns,
        );

        $core = Settings::fromArray(['standard' => 'WordPress-Core'])->excludePatterns;
        self::assertSame(['*'], $core['WordPress.Security.EscapeOutput'] ?? null);
        self::assertSame(['*'], $core['Generic.PHP.ForbiddenFunctions'] ?? null);
        self::assertArrayNotHasKey('WordPress.WP.I18n', $core);
        // Message codes WordPress-Core sets to severity 0 (WordPress-Extra restores them).
        self::assertSame(['*'], $core['PSR2.Classes.PropertyDeclaration.Underscore'] ?? null);
        self::assertArrayNotHasKey('PSR2.Classes.PropertyDeclaration', $core);

        // phpcs finds a standard regardless of case on macOS and Windows.
        self::assertSame('WordPress-Extra', Settings::fromArray(['standard' => 'wordpress-EXTRA'])->standard);
    }

    public function testProblemsNameEachInvalidSetting(): void
    {
        self::assertSame(
            [],
            Settings::problems([
                'standard' => 'wordpress-core',
                'text-domains' => 'akismet',
                'prefixes' => ['a', 'b'],
                'custom-capabilities' => 'manage_things',
                'max-posts-per-page' => '50',
                'is-theme' => 'true',
                'honor-phpcs-comments' => false,
                'minimum-wp-version' => '6.5',
                'exclude-patterns' => ['WordPress.WP.I18n' => '*/legacy/*'],
                'exclude-groups' => [],
                'levels' => ['wordpress/capital-p-dangit' => 'error'],
            ]),
        );

        self::assertSame(
            [
                'unknown setting `text-domain`.',
                '`standard` must be WordPress, WordPress-Core or WordPress-Extra, not "WordPress-Extar".',
                '`levels` must be an object of rule code => level, such as {"wordpress/capital-p-dangit": "error"}, not "warning".',
                '`text-domains` must be a string or a list of strings, not 5.',
                '`custom-test-classes` must be a string or a list of strings, not [1].',
                '`max-posts-per-page` must be an integer, not "many".',
                '`is-theme` must be true or false, not "yes".',
                '`minimum-wp-version` must be a string, not 6.5.',
                '`exclude-patterns` must be an object whose values are a string or a list of strings, not ["*"].',
            ],
            Settings::problems([
                'text-domain' => 'akismet',
                'standard' => 'WordPress-Extar',
                'levels' => 'warning',
                'text-domains' => 5,
                'custom-test-classes' => [1],
                'max-posts-per-page' => 'many',
                'is-theme' => 'yes',
                'minimum-wp-version' => 6.5,
                'exclude-patterns' => ['*'],
            ]),
        );

        foreach (['', 'WordPress-Docs', ['WordPress']] as $standard) {
            self::assertCount(1, Settings::problems(['standard' => $standard]));
        }

        self::assertCount(1, Settings::problems(['levels' => ['warning']]));
        self::assertCount(1, Settings::problems(['levels' => ['wordpress/yoda-conditions' => 3]]));
    }
}
