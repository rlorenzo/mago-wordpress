<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use Mago\Sdk\Reporting\Level;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\LeveledRule;
use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Internal\SniffMap;
use Rlorenzo\MagoWordPress\Internal\WordPress\Levels;
use Rlorenzo\MagoWordPress\Settings;
use Rlorenzo\MagoWordPress\WordPressExtension;

use function array_key_exists;
use function array_keys;
use function count;
use function in_array;

final class LevelsTest extends TestCase
{
    /**
     * Rules whose sniff reports at both levels, or decides at runtime (deprecations against
     * `minimum_wp_version`, dynamic names), with the level each rule takes.
     */
    private const MIXED = [
        'generic/disallow-alternative-php-tags' => Level::Warning, // two Maybe* warnings, one error
        'wordpress/capabilities' => Level::Error,
        'wordpress/enqueued-resource-parameters' => Level::Warning,
        'wordpress/nonce-verification' => Level::Error,
        'wordpress/parentheses-spacing' => Level::Error, // formatting sniffs (all errors) outside SniffMap
        'wordpress/prefix-all-globals' => Level::Error,
        'wordpress/prepared-sql-placeholders' => Level::Error,
        'wordpress/prepared-sql-unquoted-complex-placeholder' => Level::Warning, // its one code
        'wordpress/type-casts' => Level::Error,
        'wordpress/valid-hook-name' => Level::Error,
        'wordpress/valid-post-type-slug' => Level::Error,
        'wordpress/wp-date-time' => Level::Error,
        'wordpress/wp-deprecated-classes' => Level::Error,
        'wordpress/wp-deprecated-functions' => Level::Error,
        'wordpress/wp-deprecated-parameter-values' => Level::Error,
        'wordpress/wp-deprecated-parameters' => Level::Error,
        'wordpress/wp-i18n' => Level::Error,
    ];

    /**
     * Each rule reports at the level phpcs gives its sniffs' message codes, as generated into
     * `Levels` from the WPCS source; a rule over a mixed sniff takes the level in MIXED.
     */
    public function testRuleLevelsFollowWpcs(): void
    {
        $sniffs = [];
        foreach (SniffMap::RULES as $sniff => $codes) {
            foreach ($codes as $code) {
                $sniffs[$code][] = $sniff;
            }
        }

        foreach (WordPressExtension::create()->linterRules as $rule) {
            $definition = $rule->getDefinition();
            $levels = [];
            foreach ($sniffs[$definition->code] ?? [] as $sniff) {
                $levels += Levels::SNIFFS[$sniff];
            }
            $found = array_keys(array_flip($levels));
            $uniform = count($found) === 1 ? $found[0] : null;
            $expected = match ($uniform) {
                'error' => Level::Error,
                'warning' => Level::Warning,
                default => self::MIXED[$definition->code] ?? null,
            };

            self::assertSame($expected, $definition->defaultLevel, $definition->code);
            self::assertSame(
                !in_array($uniform, ['error', 'warning'], strict: true),
                array_key_exists($definition->code, self::MIXED),
                "{$definition->code} is listed in MIXED exactly when WPCS mixes levels",
            );
        }
    }

    public function testLevelsSettingRelevelsRulesAndReportsMistakes(): void
    {
        $settings = Settings::fromArray(['levels' => [
            'wordpress/capital-p-dangit' => ' Error',
            'wordpress/yoda-conditions' => 'loud',
            'wordpress/safe-redirect' => 3,
            'wordpress/nope' => 'note',
        ]]);
        [$rules, $problems] = LeveledRule::apply(WordPressExtension::create()->linterRules, $settings->levels);

        $levels = [];
        foreach ($rules as $rule) {
            $levels[$rule->getDefinition()->code] = $rule->getDefinition()->defaultLevel;
        }

        self::assertSame(Level::Error, $levels['wordpress/capital-p-dangit']);
        self::assertSame(Level::Error, $levels['wordpress/yoda-conditions']);
        self::assertSame(Level::Warning, $levels['wordpress/safe-redirect']);
        self::assertSame(
            [
                'levels: `wordpress/yoda-conditions` has level `loud`; use error, warning, note or help.',
                'levels: `wordpress/safe-redirect` has level ``; use error, warning, note or help.',
                'levels: unknown rule `wordpress/nope`; use a rule code such as `wordpress/capital-p-dangit`.',
            ],
            $problems,
        );
    }

    public function testPhpcsTypeOnAWholeSniffBecomesLevels(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <ruleset name="Example">
              <rule ref="WordPress.WP.CapitalPDangit"><type>error</type></rule>
              <rule ref="WordPress.PHP.DevelopmentFunctions"><type>warning</type></rule>
              <rule ref="WordPress.WP.I18n.MissingTranslatorsComment"><type>warning</type></rule>
            </ruleset>
            XML;

        // Not the core no-debug-symbols (migrate levels it in mago.toml), and not from the
        // <type> on one message code: a rule has one level.
        self::assertSame(
            ['wordpress/capital-p-dangit' => 'error', 'wordpress/discouraged-wp-functions' => 'warning'],
            Settings::fromArray(PhpcsRuleset::values($xml))->levels,
        );
    }
}
