<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsMigration;
use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Settings;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PhpcsMigrationTest extends TestCase
{
    private const RULESET = <<<'XML'
        <?xml version="1.0"?>
        <ruleset name="Example">
          <file>.</file>
          <exclude-pattern>/vendor/*</exclude-pattern>
          <exclude-pattern type="relative">^build/*</exclude-pattern>
          <exclude-pattern>/themes/(?!twenty)*</exclude-pattern>
          <arg name="parallel" value="8"/>
          <config name="testVersion" value="7.4-"/>
          <config name="minimum_wp_version" value="6.2"/>
          <rule ref="WordPress-Extra"/>
          <rule ref="WordPress.WP.I18n">
            <properties><property name="text_domain" type="array"><element value="my-plugin"/></property></properties>
          </rule>
          <rule ref="WordPress.Security.EscapeOutput"><exclude-pattern>/templates/*</exclude-pattern></rule>
          <rule ref="WordPress.DB.PreparedSQL"><type>warning</type></rule>
          <rule ref="WordPress.Files.FileName.InvalidClassFileName"><type>warning</type></rule>
          <rule ref="WordPress.WP.AlternativeFunctions.json_encode_json_encode"><exclude-pattern>*</exclude-pattern></rule>
          <rule ref="WordPress.PHP.NoSilencedErrors">
            <properties><property name="customAllowedFunctionsList" type="array"><element value="ftp_connect"/></property></properties>
          </rule>
          <rule ref="Generic.PHP.DiscourageGoto"/>
          <rule ref="WooCommerce-Core"/>
        </ruleset>
        XML;

    /**
     * @return iterable<string, array{string, bool, null|string}>
     */
    public static function patterns(): iterable
    {
        yield 'unanchored directory' => ['/vendor/*', false, '*/vendor/*'];
        yield 'anchored relative' => ['^build/*', true, 'build/*'];
        yield 'escaped dot is literal, open end' => ['/src/deprecated\.php', false, '*/src/deprecated.php*'];
        yield 'bare dot is any character' => ['/blocks/*.asset.php', false, '*/blocks/*?asset?php*'];
        yield 'dollar closes the end' => ['/wp-config\.php$', false, '*/wp-config.php'];
        yield 'bare relative name' => ['tests/cli/', false, '*tests/cli/*'];
        yield 'lookahead' => ['/themes/(?!twenty)*', false, null];
        yield 'regex class' => ['/tests/\d+/*', false, null];
        yield 'anchored absolute' => ['^/home/*', false, null];
    }

    #[DataProvider('patterns')]
    public function testGlobTranslation(string $pattern, bool $relative, ?string $glob): void
    {
        self::assertSame($glob, PhpcsMigration::glob($pattern, $relative));
    }

    public function testMigratesARuleset(): void
    {
        $result = PhpcsMigration::migrate(self::RULESET, 'phpcs.xml');
        self::assertNotNull($result);

        $toml = $result['toml'];
        self::assertStringContainsString('extends = "vendor/rlorenzo/mago-wordpress/wordpress.mago.toml"', $toml);
        self::assertStringContainsString('paths = ["."]', $toml);
        self::assertStringContainsString('"*/vendor/*",', $toml);
        self::assertStringContainsString('"build/*",', $toml);
        // Mago's core rules get [linter.rules] entries; a partial override keeps the shipped enable.
        self::assertStringContainsString('no-unescaped-output = { exclude = ["templates/*", "*/templates/*"] }', $toml);
        self::assertStringContainsString('prepared-sql = { enabled = true, level = "warning" }', $toml);
        // WordPress-Extra leaves out the three WordPress-only sniffs.
        self::assertStringContainsString('validated-sanitized-input = { enabled = false }', $toml);
        // ...and WordPress-Docs, which the shipped missing-docs stands in for.
        self::assertStringContainsString('missing-docs = { enabled = false }', $toml);

        self::assertSame(['my-plugin'], $result['extra']['text-domains'] ?? null);
        self::assertSame('6.2', $result['extra']['minimum-wp-version'] ?? null);
        self::assertSame(['*'], $result['extra']['exclude-patterns']['WordPress.DB.SlowDBQuery'] ?? null);
        // Codes that only reach core rules stay out of the extension's settings.
        self::assertStringNotContainsString('WordPress.Security.EscapeOutput', (string) json_encode($result['extra']));

        $unmapped = implode("\n", $result['unmapped']);
        self::assertStringContainsString('(?!twenty)', $unmapped);
        self::assertStringContainsString('json_encode_json_encode', $unmapped);
        self::assertStringContainsString('<type> on WordPress.Files.FileName.InvalidClassFileName', $unmapped);
        self::assertStringContainsString('customAllowedFunctionsList', $unmapped);
        self::assertStringContainsString('Generic.PHP.DiscourageGoto', $unmapped);
        self::assertStringContainsString('WooCommerce-Core', $unmapped);
        self::assertStringContainsString('testVersion', $unmapped);
        self::assertStringContainsString('parallel', $unmapped);
        self::assertStringNotContainsString('text_domain', $unmapped);
    }

    public function testRulesetExclusionsBecomeExcludePatterns(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="Example">
              <rule ref="WordPress-Core">
                <exclude name="WordPress.PHP.YodaConditions"/>
                <exclude phpcbf-only="true" name="WordPress.PHP.TypeCasts"/>
              </rule>
              <rule ref="WordPress.WP.EnqueuedResources"/>
              <rule ref="WordPress.Files.FileName.InvalidClassFileName">
                <exclude-pattern>/tests/*</exclude-pattern>
                <exclude-pattern type="relative">^legacy/*</exclude-pattern>
              </rule>
              <rule ref="WordPress.WP.I18n.MissingTranslatorsComment">
                <severity>0</severity>
                <exclude-pattern>/ignored/*</exclude-pattern>
              </rule>
              <rule ref="Generic.Files.LineEndings"><exclude-pattern>*</exclude-pattern></rule>
            </ruleset>
            XML;

        $patterns = Settings::fromArray(PhpcsRuleset::values($xml))->excludePatterns;

        // WordPress-Core leaves out the Extra and WordPress-only sniffs; a sniff ref brings one back.
        self::assertSame(['*'], $patterns['WordPress.WP.GlobalVariablesOverride'] ?? null);
        self::assertSame(['*'], $patterns['WordPress.DB.SlowDBQuery'] ?? null);
        self::assertArrayNotHasKey('WordPress.WP.EnqueuedResources', $patterns);
        self::assertArrayNotHasKey('WordPress.WP.I18n', $patterns);
        self::assertSame(['*'], $patterns['WordPress.PHP.YodaConditions'] ?? null);
        self::assertArrayNotHasKey('WordPress.PHP.TypeCasts', $patterns);
        self::assertSame(['/tests/*'], $patterns['WordPress.Files.FileName.InvalidClassFileName'] ?? null);
        self::assertSame(['*'], $patterns['WordPress.WP.I18n.MissingTranslatorsComment'] ?? null);
        self::assertArrayNotHasKey('Generic.Files.LineEndings', $patterns);
    }

    public function testBlankExcludePatternIsSkipped(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="x">
              <rule ref="WordPress"/>
              <rule ref="WordPress.WP.I18n"><exclude-pattern> </exclude-pattern></rule>
            </ruleset>
            XML;
        $xpath = PhpcsRuleset::load($xml);
        self::assertNotNull($xpath);

        // An empty pattern would exclude the code in every file.
        self::assertSame([], PhpcsRuleset::excludePatterns($xpath));
    }

    public function testDocsStandardKeepsMissingDocs(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="x">
              <rule ref="WordPress-Extra"/>
              <rule ref="WordPress-Docs"/>
            </ruleset>
            XML;
        $result = PhpcsMigration::migrate($xml, 'phpcs.xml');
        self::assertNotNull($result);

        self::assertStringNotContainsString('missing-docs', $result['toml']);
    }

    public function testWordPressStandardExcludesNothing(): void
    {
        $xml = '<?xml version="1.0"?><ruleset name="x"><rule ref="WordPress"/></ruleset>';

        self::assertSame([], Settings::fromArray(PhpcsRuleset::values($xml))->excludePatterns);
    }

    public function testWriteMergesComposerAndRefusesToOverwriteMagoToml(): void
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('mago-wordpress-migrate-', more_entropy: true);
        mkdir($dir);
        file_put_contents("{$dir}/phpcs.xml.dist", self::RULESET);
        file_put_contents(
            "{$dir}/composer.json",
            data: "{\n\t\"name\": \"a/b\",\n\t\"require\": {},\n\t\"extra\": {\"mago-wordpress\": {\"honor-phpcs-comments\": false}}\n}\n",
        );

        try {
            self::assertIsString(PhpcsMigration::run([$dir], $dir));
            self::assertFileDoesNotExist("{$dir}/mago.toml");

            self::assertIsString(PhpcsMigration::run([$dir, '--write'], $dir));
            self::assertFileExists("{$dir}/mago.toml");
            $composer = (string) file_get_contents("{$dir}/composer.json");
            self::assertStringContainsString("\n\t\"require\": {},", $composer);
            self::assertStringContainsString('"honor-phpcs-comments": false', $composer);
            self::assertStringContainsString("\t\t\t\"text-domains\": [", $composer);

            self::assertSame(1, PhpcsMigration::run([$dir, '--write'], $dir));
            self::assertIsString(PhpcsMigration::run([$dir, '--write', '--force'], $dir));
        } finally {
            $files = glob("{$dir}/*");
            foreach ($files === false ? [] : $files as $file) {
                unlink($file);
            }

            rmdir($dir);
        }
    }
}
