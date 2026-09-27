<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedClassesRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpDeprecatedFunctionsRule;
use Rlorenzo\MagoWordPress\Settings;

/**
 * Corpus fixtures share one `minimum-wp-version` (the project default,
 * `6.0`), so a case that needs a different threshold is asserted here
 * instead, straight against each rule's private gate.
 */
final class WpDeprecatedRulesTest extends TestCase
{
    public function testFunctionsGateFlagsADeprecationAtOrBeforeTheMinimum(): void
    {
        $rule = new WpDeprecatedFunctionsRule(new Settings(minimumWpVersion: '4.5'));

        // get_currentuserinfo() was deprecated in 4.5, the configured minimum.
        self::assertTrue(self::isReportable($rule, '4.5.0'));
    }

    public function testFunctionsGateSkipsADeprecationAfterTheMinimum(): void
    {
        $rule = new WpDeprecatedFunctionsRule(new Settings(minimumWpVersion: '4.4'));

        self::assertFalse(self::isReportable($rule, '4.5.0')); // get_currentuserinfo
        self::assertFalse(self::isReportable($rule, '4.6.0')); // wp_get_sites
        self::assertFalse(self::isReportable($rule, '6.2.0')); // get_page_by_title
    }

    public function testFunctionsGateComparesVersionsNumericallyNotLexically(): void
    {
        $rule = new WpDeprecatedFunctionsRule(new Settings(minimumWpVersion: '4.10'));

        // "4.10" must be treated as greater than "4.9" (wp_get_sites, 4.6), not
        // compared as strings.
        self::assertTrue(self::isReportable($rule, '4.6.0')); // wp_get_sites
        self::assertFalse(self::isReportable($rule, '6.2.0')); // get_page_by_title
    }

    public function testFunctionsGateFlagsEverythingWhenTheMinimumIsUnparsable(): void
    {
        $rule = new WpDeprecatedFunctionsRule(new Settings(minimumWpVersion: 'banana'));

        self::assertTrue(self::isReportable($rule, '6.2.0')); // get_page_by_title
    }

    public function testClassesGateFlagsADeprecationAtOrBeforeTheMinimum(): void
    {
        $rule = new WpDeprecatedClassesRule(new Settings(minimumWpVersion: '4.4'));

        // WP_User_Search was deprecated in 3.1.
        self::assertTrue(self::isReportable($rule, '3.1.0'));
    }

    public function testClassesGateSkipsADeprecationAfterTheMinimum(): void
    {
        $rule = new WpDeprecatedClassesRule(new Settings(minimumWpVersion: '4.4'));

        // WP_Http_Curl (deprecated 6.4) stands in for Requests, which is not in
        // Lists::DEPRECATED_CLASSES.
        self::assertFalse(self::isReportable($rule, '6.4.0'));
    }

    public function testClassesGateFlagsEverythingWhenTheMinimumIsUnparsable(): void
    {
        $rule = new WpDeprecatedClassesRule(new Settings(minimumWpVersion: 'latest'));

        self::assertTrue(self::isReportable($rule, '6.4.0'));
    }

    private static function isReportable(object $rule, string $deprecatedSince): bool
    {
        $method = new ReflectionMethod($rule, 'isReportable');

        return $method->invoke($rule, $deprecatedSince) === true;
    }
}
