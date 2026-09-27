<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Rlorenzo\MagoWordPress\Linter\Rules\PreparedSqlPlaceholdersRule;
use Rlorenzo\MagoWordPress\Linter\Rules\PrefixAllGlobalsRule;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;
use Rlorenzo\MagoWordPress\Linter\Rules\WpI18nRule;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * @api
 */
final class WordPressExtension
{
    private const VERSION = '0.1.0';

    private function __construct() {}

    public static function create(?Settings $settings = null): Extension
    {
        $settings ??= new Settings();

        return new Extension(
            identifier: 'rlorenzo/mago-wordpress',
            name: 'WordPress',
            version: self::VERSION,
            linterRules: [
                new PreparedSqlPlaceholdersRule(),
                new SafeRedirectRule(),
                new WpI18nRule($settings),
                new PrefixAllGlobalsRule($settings),
            ],
        );
    }
}
