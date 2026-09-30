<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\Internal\WordPress\WpVersion;
use Rlorenzo\MagoWordPress\Settings;

/**
 * Corpus fixtures share one `minimum-wp-version` (the project default,
 * `6.7`, WPCS 3.4.1's default), so thresholds other than the default are asserted here, against
 * the gate every deprecation rule and the `%i` check share.
 */
final class WpVersionTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string, bool}>
     */
    public static function versions(): iterable
    {
        yield 'short minimum equals padded version' => ['4.5', '4.5.0', true];
        yield 'padded minimum equals short version' => ['4.5.0', '4.5', true];
        yield 'major only' => ['6', '6.0', true];
        yield 'deprecated after the minimum' => ['4.4', '4.5.0', false];
        yield 'deprecated well after the minimum' => ['4.4', '6.4.0', false];
        yield 'deprecated before the minimum' => ['4.4', '3.1.0', true];
        yield 'numeric, not lexical, before' => ['4.10', '4.6.0', true];
        yield 'numeric, not lexical, after' => ['4.10', '6.2.0', false];
        yield 'default minimum reaches 6.1' => [null, '6.1.0', true];
        yield 'default minimum skips 6.9' => [null, '6.9.0', false];
        yield 'minimum reaches a patch release' => ['6.9', '6.5.3', true];
        yield 'minimum equals the version' => ['6.6', '6.6.0', true];
        yield 'minimum just below' => ['6.1', '6.2.0', false];
        yield 'newer patch minimum' => ['6.2.1', '6.2.0', true];
        yield 'empty minimum counts as reached' => ['', '99.0', true];
        yield 'unparsable minimum counts as reached' => ['banana', '6.2.0', true];
    }

    #[DataProvider('versions')]
    public function testReached(?string $setting, string $version, bool $expected): void
    {
        $settings = $setting === null ? new Settings() : new Settings(minimumWpVersion: $setting);

        self::assertSame($expected, WpVersion::reached($settings->normalizedMinimumWpVersion(), $version));
    }
}
