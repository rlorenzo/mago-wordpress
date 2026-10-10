<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\SettingsDiscovery;

use function file_put_contents;

/**
 * @mago-expect lint:too-many-methods
 */
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

    public function testWorkerStandardIsTheDefaultComposerOverrides(): void
    {
        self::assertSame('WordPress-Extra', SettingsDiscovery::in($this->directory, 'WordPress-Extra')->standard);

        file_put_contents($this->directory . '/composer.json', data: '{"extra": {"mago-wordpress": {}}}');
        self::assertSame('WordPress-Core', SettingsDiscovery::in($this->directory, 'WordPress-Core')->standard);

        file_put_contents(
            $this->directory . '/composer.json',
            data: '{"extra": {"mago-wordpress": {"standard": "WordPress"}}}',
        );
        self::assertSame('WordPress', SettingsDiscovery::in($this->directory, 'WordPress-Core')->standard);
    }

    public function testPhpcsRulesetStandardWinsOverTheWorkerStandard(): void
    {
        // No standard named: the worker's applies.
        file_put_contents($this->directory . '/phpcs.xml', self::ruleset('from_phpcs'));
        $settings = SettingsDiscovery::in($this->directory, 'WordPress-Core');
        self::assertSame(['*'], $settings->excludePatterns['WordPress.Security.EscapeOutput'] ?? null);

        // Built on WordPress-Extra: EscapeOutput runs, whatever the preset says.
        file_put_contents(
            $this->directory . '/phpcs.xml',
            data: '<?xml version="1.0"?><ruleset name="x"><rule ref="WordPress-Extra"/></ruleset>',
        );
        $settings = SettingsDiscovery::in($this->directory, 'WordPress-Core');
        self::assertArrayNotHasKey('WordPress.Security.EscapeOutput', $settings->excludePatterns);
        self::assertSame(['*'], $settings->excludePatterns['WordPress.DB.SlowDBQuery'] ?? null);
    }

    public function testInvalidComposerSettingsThrowOneLineEach(): void
    {
        file_put_contents(
            $this->directory . '/composer.json',
            data: '{"extra": {"mago-wordpress": {"standard": "WordPress-Extar", "nope": 1}}}',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "composer.json extra.mago-wordpress: `standard` must be WordPress, WordPress-Core or WordPress-Extra, not \"WordPress-Extar\".\n"
            . 'composer.json extra.mago-wordpress: unknown setting `nope`.',
        );
        SettingsDiscovery::in($this->directory);
    }

    public function testNonObjectComposerSettingsThrow(): void
    {
        file_put_contents($this->directory . '/composer.json', data: '{"extra": {"mago-wordpress": "WordPress"}}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('composer.json extra.mago-wordpress: must be an object.');
        SettingsDiscovery::in($this->directory);
    }

    public function testComposerSettingsListThrowsWhileEmptyObjectDoesNot(): void
    {
        foreach (['[]', '["a"]'] as $block) {
            file_put_contents(
                $this->directory . '/composer.json',
                data: '{"extra": {"mago-wordpress": ' . $block . '}}',
            );
            try {
                SettingsDiscovery::in($this->directory);
                self::fail("{$block} was accepted");
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('must be an object', $exception->getMessage());
            }
        }

        file_put_contents($this->directory . '/composer.json', data: '{"extra": {"mago-wordpress": {}}}');
        SettingsDiscovery::in($this->directory);
    }

    public function testInvalidWorkerStandardThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SettingsDiscovery::in($this->directory, 'WordPress-Docs');
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
