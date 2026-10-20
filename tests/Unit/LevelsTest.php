<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Reporting\Level;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\LeveledRule;
use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Internal\SniffMap;
use Rlorenzo\MagoWordPress\Internal\WordPress\Levels;
use Rlorenzo\MagoWordPress\Settings;
use Rlorenzo\MagoWordPress\WordPressExtension;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function in_array;
use function str_ends_with;

final class LevelsTest extends TestCase
{
    /**
     * Rules whose sniff reports at both levels, or decides at runtime (deprecations against
     * `minimum_wp_version`, dynamic names), with the level each rule takes.
     */
    private const MIXED = [
        'generic/closing-declaration-comment' => Level::Error, // Abstract and MissingBrace are warnings, for parse errors the rule does not report
        'generic/opening-brace-same-line' => Level::Error, // MissingBrace is a warning, for a parse error the rule does not report
        'generic/class-comment' => Level::Error, // TagNotAllowed is a warning, but WordPress-Docs turns it off
        'generic/variable-comment' => Level::Error, // TagNotAllowed is a warning, but WordPress-Docs turns it off
        'generic/disallow-short-open-tag' => Level::Error, // Found and EchoFound are errors; PossibleFound, a warning, is not ported
        'generic/disallow-alternative-php-tags' => Level::Warning, // two Maybe* warnings, one error
        'generic/inline-control-structure' => Level::Error, // NotAllowed; Discouraged only with error=false
        'generic/forbidden-functions' => Level::Error, // the sniff's `error` property, true unless a ruleset changes it
        'generic/unnecessary-string-concat' => Level::Error, // the sniff's `error` property, true unless a ruleset changes it
        'wordpress/capabilities' => Level::Error, // Deprecated: error or warning against minimum_wp_version
        'wordpress/enqueued-resource-parameters' => Level::Warning,
        'wordpress/array-declaration-spacing' => Level::Error, // addFixableError throughout, outside SniffMap
        'wordpress/object-operator-spacing' => Level::Error, // addFixableError throughout, inherited from the Squiz sniff
        'wordpress/operator-spacing' => Level::Error, // addFixableError throughout, inherited from the Squiz sniff
        'generic/arbitrary-parentheses-spacing' => Level::Error, // FoundEmpty is a warning
        'wordpress/array-alignment' => Level::Warning, // addFixableWarning throughout, outside SniffMap
        'generic/anon-class-keyword-spacing' => Level::Error, // PHPCSExtra reports through SpacesFixer::checkAndFix() (check() here), as errors; Levels has no codes for it
        'generic/type-separator-spacing' => Level::Error, // PHPCSExtra reports through SpacesFixer::checkAndFix() (check() here), as errors; Levels has no codes for it
        'generic/use-statement-keyword-spacing' => Level::Error, // PHPCSExtra reports through SpacesFixer::checkAndFix() (check() here), as errors; Levels has no codes for it
        'wordpress/array-brace-spacing' => Level::Error, // PHPCSExtra reports through SpacesFixer::checkAndFix() (check() here), as errors; Levels has no codes for it
        'wordpress/parentheses-spacing' => Level::Error, // formatting sniffs (all errors) outside SniffMap
        'wordpress/prefix-all-globals' => Level::Error,
        'wordpress/prepared-sql-unquoted-complex-placeholder' => Level::Warning, // its one code
        'wordpress/statement-alignment' => Level::Warning, // addFixableWarning throughout; Levels also lists the error-level properties check
        'wordpress/type-casts' => Level::Error,
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

        $rules = WordPressExtension::create()->linterRules;
        $codes = array_flip(array_map(static fn(Rule $rule): string => $rule->getDefinition()->code, $rules));
        foreach ($rules as $rule) {
            $definition = $rule->getDefinition();
            $levels = [];
            foreach ($sniffs[$definition->code] ?? [] as $sniff) {
                $levels += Levels::SNIFFS[$sniff];
            }
            // A split rule's `-warning` companion takes the codes WPCS reports as warnings.
            if (str_ends_with($definition->code, '-warning')) {
                $levels = array_filter($levels, static fn(string $level): bool => $level === 'warning');
            } elseif (array_key_exists($definition->code . '-warning', $codes)) {
                $levels = array_filter($levels, static fn(string $level): bool => $level !== 'warning');
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
