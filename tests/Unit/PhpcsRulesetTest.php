<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Internal\SettingsDiscovery;
use Rlorenzo\MagoWordPress\Settings;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function unlink;

final class PhpcsRulesetTest extends TestCase
{
    use TempProject;

    public function testNestedRulesetsAreMergedAndCyclesStop(): void
    {
        $dir = $this->directory;
        mkdir("{$dir}/sub");
        $head = '<?xml version="1.0"?><ruleset name="x">';
        // main -> sub/inner.xml (relative to main) -> ../outer.xml (relative to inner) -> main again.
        file_put_contents(
            "{$dir}/main.xml",
            $head
            . '<rule ref="./sub/inner.xml"/><rule ref="WordPress.WP.I18n"><properties><property name="text_domain" value="main"/></properties></rule></ruleset>',
        );
        file_put_contents(
            "{$dir}/sub/inner.xml",
            $head
            . '<rule ref="../outer.xml"/><rule ref="WordPress.Files.FileName.NotHyphenatedLowercase"><exclude-pattern>*/a_b\\.php$</exclude-pattern></rule></ruleset>',
        );
        file_put_contents(
            "{$dir}/outer.xml",
            $head . '<rule ref="./main.xml"/><config name="minimum_wp_version" value="6.1"/></ruleset>',
        );

        $settings = Settings::fromArray(PhpcsRuleset::values((string) file_get_contents("{$dir}/main.xml"), $dir));
        unlink("{$dir}/sub/inner.xml");
        rmdir("{$dir}/sub");

        self::assertSame(
            ['*/a_b\\.php$'],
            $settings->excludePatterns['WordPress.Files.FileName.NotHyphenatedLowercase'],
        );
        self::assertSame(['main'], $settings->textDomains);
        self::assertSame('6.1', $settings->minimumWpVersion);
        // Without a directory nothing is followed.
        self::assertSame(
            [],
            Settings::fromArray(PhpcsRuleset::values((string) file_get_contents("{$dir}/main.xml")))->excludePatterns,
        );
    }

    public function testEntryRulesetIsNotInlinedAgainWhenAnIncludeLeadsBackToIt(): void
    {
        $dir = sys_get_temp_dir() . '/mago-wp-cycle-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $head = '<?xml version="1.0"?><ruleset name="x">';
        file_put_contents(
            "{$dir}/a.xml",
            $head
            . '<rule ref="./b.xml"/><rule ref="WordPress.Files.FileName"><exclude-pattern>*/a\\.php$</exclude-pattern></rule></ruleset>',
        );
        file_put_contents("{$dir}/b.xml", $head . '<rule ref="./a.xml"/></ruleset>');

        $xpath = PhpcsRuleset::load((string) file_get_contents("{$dir}/a.xml"), $dir, 'a.xml');
        unlink("{$dir}/a.xml");
        unlink("{$dir}/b.xml");
        rmdir($dir);

        self::assertNotNull($xpath);
        self::assertSame(1.0, $xpath->evaluate('count(//exclude-pattern)'));
    }

    public function testSettingsDiscoveryFollowsNestedRulesetsInPhpcsXml(): void
    {
        file_put_contents(
            "{$this->directory}/phpcs.xml",
            '<?xml version="1.0"?><ruleset name="x"><rule ref="./other.xml"/></ruleset>',
        );
        file_put_contents(
            "{$this->directory}/other.xml",
            '<?xml version="1.0"?><ruleset name="x"><rule ref="WordPress.WP.I18n"><properties><property name="text_domain" value="nested"/></properties></rule></ruleset>',
        );

        self::assertSame(['nested'], SettingsDiscovery::in($this->directory)->textDomains);
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
        // The generic/* ports follow the same standard membership.
        self::assertSame(['*'], $patterns['Generic.CodeAnalysis.JumbledIncrementer'] ?? null);
        self::assertArrayNotHasKey('Generic.Files.ByteOrderMark', $patterns);
    }

    public function testCategoryRefsReachGenericSniffs(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="x">
              <rule ref="WordPress-Extra">
                <exclude name="Generic.CodeAnalysis"/>
                <exclude name="Generic.WhiteSpace"/>
              </rule>
              <rule ref="Squiz.PHP"><exclude-pattern>/legacy/*</exclude-pattern></rule>
              <rule ref="Universal"><severity>0</severity></rule>
            </ruleset>
            XML;

        $patterns = Settings::fromArray(PhpcsRuleset::values($xml))->excludePatterns;

        // A category or standard holding a mapped sniff counts, as phpcs applies it to every sniff under it.
        self::assertSame(['*'], $patterns['Generic.CodeAnalysis'] ?? null);
        self::assertSame(['/legacy/*'], $patterns['Squiz.PHP'] ?? null);
        self::assertSame(['*'], $patterns['Universal'] ?? null);
        // A category with nothing mapped stays out.
        self::assertArrayNotHasKey('Generic.WhiteSpace', $patterns);
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

    public function testRepeatedExcludePropertyReplacesUnlessExtended(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="x">
              <rule ref="WordPress"/>
              <rule ref="WordPress.PHP.DevelopmentFunctions">
                <properties><property name="exclude" type="array"><element value="error_log"/></property></properties>
              </rule>
              <rule ref="WordPress.PHP.DevelopmentFunctions">
                <properties><property name="exclude" type="array"><element value="prevent_path_disclosure"/></property></properties>
              </rule>
              <rule ref="WordPress.PHP.DevelopmentFunctions">
                <properties><property name="exclude" type="array" extend="true"><element value="error_log"/></property></properties>
              </rule>
              <rule ref="WordPress.WP.DiscouragedFunctions">
                <properties><property name="exclude" type="array"><element value="query_posts"/></property></properties>
              </rule>
              <rule ref="WordPress.WP.DiscouragedFunctions">
                <properties><property name="exclude" type="array"/></properties>
              </rule>
            </ruleset>
            XML;

        self::assertSame(
            ['WordPress.PHP.DevelopmentFunctions' => ['prevent_path_disclosure', 'error_log']],
            Settings::fromArray(PhpcsRuleset::values($xml))->excludeGroups,
        );
    }

    public function testWordPressStandardExcludesNothing(): void
    {
        $xml = '<?xml version="1.0"?><ruleset name="x"><rule ref="WordPress"/></ruleset>';

        self::assertSame([], Settings::fromArray(PhpcsRuleset::values($xml))->excludePatterns);
    }
}
