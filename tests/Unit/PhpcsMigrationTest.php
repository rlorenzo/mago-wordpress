<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsMigration;
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
          <rule ref="Generic.WhiteSpace.ScopeIndent"/>
          <rule ref="Generic.CodeAnalysis.JumbledIncrementer"><exclude-pattern>/legacy/*</exclude-pattern></rule>
          <rule ref="Generic.PHP.BacktickOperator"><severity>0</severity></rule>
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
        // Generic sniffs map to this package's generic/* rules and Mago core rules.
        self::assertStringNotContainsString('Generic.PHP.DiscourageGoto', $unmapped);
        self::assertStringContainsString('Generic.WhiteSpace.ScopeIndent', $unmapped);
        self::assertStringContainsString('no-shell-execute-string = { enabled = false }', $toml);
        self::assertSame(
            ['/legacy/*'],
            $result['extra']['exclude-patterns']['Generic.CodeAnalysis.JumbledIncrementer'] ?? null,
        );
        self::assertStringContainsString('WooCommerce-Core', $unmapped);
        self::assertStringContainsString('testVersion', $unmapped);
        self::assertStringContainsString('parallel', $unmapped);
        self::assertStringNotContainsString('text_domain', $unmapped);
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

    /**
     * @return iterable<string, array{string, null|string}>
     */
    public static function sharedRuleRulesets(): iterable
    {
        $only = '<rule ref="WordPress-Extra"><exclude name="Generic.PHP.LowerCaseConstant"/></rule>';
        yield 'one of the two sniffs excluded' => [$only, null];
        $both =
            '<rule ref="WordPress-Extra"><exclude name="Generic.PHP.LowerCaseConstant"/>'
            . '<exclude name="Generic.PHP.LowerCaseKeyword"/></rule>';
        yield 'both excluded' => [$both, 'lowercase-keyword = { enabled = false }'];
        $levels = '<rule ref="WordPress-Extra"/><rule ref="Generic.PHP.LowerCaseConstant"><type>warning</type></rule>';
        yield 'level on one' => [$levels, null];
    }

    #[DataProvider('sharedRuleRulesets')]
    public function testSharedCoreRuleOnlyChangesWhenItsSniffsAgree(string $rules, ?string $expected): void
    {
        $result = PhpcsMigration::migrate("<?xml version=\"1.0\"?><ruleset name=\"x\">{$rules}</ruleset>", 'phpcs.xml');
        self::assertNotNull($result);

        if ($expected === null) {
            // A disagreement is listed and the rule left alone, so nothing is silently turned off.
            self::assertStringNotContainsString('lowercase-keyword =', $result['toml']);
            self::assertStringContainsString("Mago's lowercase-keyword covers", implode("\n", $result['unmapped']));

            return;
        }

        self::assertStringContainsString($expected, $result['toml']);
    }

    public function testSniffPropertiesBecomeSettings(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="x">
              <rule ref="WordPress"/>
              <rule ref="WordPress.PHP.DevelopmentFunctions">
                <properties><property name="exclude" type="array"><element value="error_log"/></property></properties>
              </rule>
              <rule ref="WordPress.WP.DiscouragedFunctions">
                <properties><property name="exclude" type="array" value="query_posts, wp_reset_query"/></properties>
              </rule>
              <rule ref="WordPress.WP.AlternativeFunctions">
                <properties><property name="exclude" type="array"><element value="curl"/></property></properties>
              </rule>
              <rule ref="WordPress.Files.FileName">
                <properties>
                  <property name="strict_class_file_names" value="false"/>
                  <property name="is_theme" value="true"/>
                  <property name="custom_test_classes" type="array"><element value="\My\TestCase"/></property>
                </properties>
              </rule>
              <rule ref="WordPress.WP.GlobalVariablesOverride">
                <properties><property name="treat_files_as_scoped" value="true"/></properties>
              </rule>
            </ruleset>
            XML;

        $result = PhpcsMigration::migrate($xml, 'phpcs.xml');
        self::assertNotNull($result);
        self::assertSame(
            [
                'WordPress.PHP.DevelopmentFunctions' => ['error_log'],
                'WordPress.WP.DiscouragedFunctions' => ['query_posts', 'wp_reset_query'],
            ],
            $result['extra']['exclude-groups'] ?? null,
        );
        self::assertFalse($result['extra']['strict-class-file-names'] ?? null);
        self::assertTrue($result['extra']['is-theme'] ?? null);
        self::assertTrue($result['extra']['treat-files-as-scoped'] ?? null);
        self::assertSame(['\My\TestCase'], $result['extra']['custom-test-classes'] ?? null);
        // Mago's core `use-wp-functions` ports AlternativeFunctions and has no group setting.
        self::assertContains('property exclude on WordPress.WP.AlternativeFunctions: no setting', $result['unmapped']);

        $settings = Settings::fromArray($result['extra']);
        self::assertSame(['my\testcase'], $settings->customList('custom-test-classes'));
        self::assertFalse($settings->strictClassFileNames);
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
