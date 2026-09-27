<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedParametersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedParameterValuesRule;
use Rlorenzo\MagoWordPress\Settings;

/**
 * Corpus fixtures share one `minimum-wp-version` (the project default,
 * `6.0`), so an entry deprecated after that default is not flagged there.
 * This asserts that a project configured with a higher minimum does flag
 * it, straight against each rule's private gate, the same way
 * `WpDeprecatedRulesTest` covers `WpDeprecatedFunctionsRule` and
 * `WpDeprecatedClassesRule`.
 */
final class WpDeprecatedParameterRulesTest extends TestCase
{
    public function testParametersGateSkipsADeprecationAfterTheDefaultMinimum(): void
    {
        $rule = new WpDeprecatedParametersRule(new Settings());

        // global_terms() (6.1.0), inject_ignored_hooked_blocks_metadata_attributes()
        // (6.5.3), wp_render_elements_support_styles() (6.6.0), and
        // _wp_can_use_pcre_u()'s $set (6.9.0) were all deprecated after the
        // default minimum-wp-version (6.0).
        self::assertFalse(self::isReportable($rule, '6.1.0'));
        self::assertFalse(self::isReportable($rule, '6.5.3'));
        self::assertFalse(self::isReportable($rule, '6.6.0'));
        self::assertFalse(self::isReportable($rule, '6.9.0'));
    }

    public function testParametersGateFlagsThatDeprecationOnceTheMinimumReachesIt(): void
    {
        $rule = new WpDeprecatedParametersRule(new Settings(minimumWpVersion: '6.9'));

        self::assertTrue(self::isReportable($rule, '6.1.0'));
        self::assertTrue(self::isReportable($rule, '6.5.3'));
        self::assertTrue(self::isReportable($rule, '6.6.0'));
        self::assertTrue(self::isReportable($rule, '6.9.0'));
    }

    public function testParameterValuesGateSkipsADeprecationAfterTheDefaultMinimum(): void
    {
        $rule = new WpDeprecatedParameterValuesRule(new Settings());

        // wp_get_typography_font_size_value()'s boolean $settings values were
        // deprecated in 6.6, after the default minimum-wp-version (6.0).
        self::assertFalse(self::isReportable($rule, '6.6.0'));
    }

    public function testParameterValuesGateFlagsThatDeprecationOnceTheMinimumReachesIt(): void
    {
        $rule = new WpDeprecatedParameterValuesRule(new Settings(minimumWpVersion: '6.6'));

        self::assertTrue(self::isReportable($rule, '6.6.0'));
    }

    private static function isReportable(object $rule, string $deprecatedSince): bool
    {
        $method = new ReflectionMethod($rule, 'isReportable');

        return $method->invoke($rule, $deprecatedSince) === true;
    }
}
