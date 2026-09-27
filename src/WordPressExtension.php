<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Rlorenzo\MagoWordPress\Linter\Rules\SafeRedirectRule;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * @api
 */
final class WordPressExtension
{
    private const VERSION = '0.1.0';

    private function __construct() {}

    /**
     * No rule reads $settings yet; the worker already passes the discovered settings in.
     */
    public static function create(?Settings $settings = null): Extension
    {
        return new Extension(
            identifier: 'rlorenzo/mago-wordpress',
            name: 'WordPress',
            version: self::VERSION,
            linterRules: [
                new SafeRedirectRule(),
            ],
        );
    }
}
