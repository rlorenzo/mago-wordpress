<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\SettingsDiscovery;

use function file_put_contents;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class SettingsDiscoveryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('mago-wordpress-', more_entropy: true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (['composer.json', 'phpcs.xml', '.phpcs.xml.dist'] as $name) {
            if (!is_file("{$this->directory}/{$name}")) {
                continue;
            }

            unlink("{$this->directory}/{$name}");
        }

        rmdir($this->directory);
    }

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
