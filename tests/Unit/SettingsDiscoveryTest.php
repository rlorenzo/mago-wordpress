<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\SettingsDiscovery;

use function file_put_contents;

final class SettingsDiscoveryTest extends TestCase
{
    use TempProject;

    public function testComposerExtraWinsOverPhpcs(): void
    {
        file_put_contents(
            $this->directory . '/composer.json',
            data: '{"extra": {"mago-wordpress": {"prefixes": ["from_composer"]}}}',
        );
        file_put_contents($this->directory . '/phpcs.xml', self::ruleset('from_phpcs'));

        self::assertSame(['from_composer'], SettingsDiscovery::in($this->directory)->prefixes);
    }

    public function testFallsBackToPhpcsWithoutComposerExtra(): void
    {
        file_put_contents($this->directory . '/composer.json', data: '{"name": "acme/plugin"}');
        file_put_contents($this->directory . '/.phpcs.xml.dist', self::ruleset('from_phpcs'));

        self::assertSame(['from_phpcs'], SettingsDiscovery::in($this->directory)->prefixes);
    }

    public function testMalformedComposerJsonFallsBackToPhpcs(): void
    {
        file_put_contents($this->directory . '/composer.json', data: '{"extra": ');
        file_put_contents($this->directory . '/phpcs.xml', self::ruleset('from_phpcs'));

        self::assertSame(['from_phpcs'], SettingsDiscovery::in($this->directory)->prefixes);
    }

    public function testDefaultsWithoutConfiguration(): void
    {
        $settings = SettingsDiscovery::in($this->directory);

        self::assertSame([], $settings->prefixes);
        self::assertSame('6.7', $settings->minimumWpVersion);
    }

    public function testEmptyComposerExtraDoesNotFallBackToPhpcs(): void
    {
        file_put_contents($this->directory . '/composer.json', data: '{"extra": {"mago-wordpress": {}}}');
        file_put_contents($this->directory . '/phpcs.xml', self::ruleset('from_phpcs'));

        self::assertSame([], SettingsDiscovery::in($this->directory)->prefixes);
    }

    private static function ruleset(string $prefix): string
    {
        return <<<XML
            <?xml version="1.0"?>
            <ruleset name="Example">
              <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
                <properties>
                  <property name="prefixes" type="array"><element value="{$prefix}"/></property>
                </properties>
              </rule>
            </ruleset>
            XML;
    }
}
