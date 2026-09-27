<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress;

use Mago\Sdk\Extension;
use Rlorenzo\MagoWordPress\Analyzer\WordPressPlugin;
use Rlorenzo\MagoWordPress\Linter\Rules\NoLegacyHelperRule;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * @api
 */
final class WordPressExtension
{
    private const VERSION = '0.1.0';

    private function __construct() {}

    public static function create(): Extension
    {
        return new Extension(
            identifier: 'rlorenzo/mago-wordpress',
            name: 'Mago WordPress Extension',
            version: self::VERSION,
            linterRules: [new NoLegacyHelperRule()],
            analyzerPlugins: [new WordPressPlugin()],
        );
    }
}
