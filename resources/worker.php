<?php

/**
 * Ready-made worker entrypoint, so one TOML block adds this extension:
 *
 *     [extension-hosts.wordpress]
 *     command = ["php", "vendor/rlorenzo/mago-wordpress/resources/worker.php"]
 *
 * Settings (text domains, prefixes, minimum WordPress version, custom escaping
 * functions) are read from the project's composer.json `extra.mago-wordpress`,
 * falling back to its phpcs.xml properties. An optional `--standard=WordPress-Core`
 * or `--standard=WordPress-Extra` argument sets the default `standard` setting.
 */

declare(strict_types=1);

use Mago\Sdk\Worker;
use Rlorenzo\MagoWordPress\Internal\SettingsDiscovery;
use Rlorenzo\MagoWordPress\WordPressExtension;

// stdout carries the protocol frames: a fatal error (e.g. memory exhausted) printed there reads as
// "invalid extension frame magic"; on stderr, Mago shows it when the worker exits.
// @mago-expect lint:no-ini-set
ini_set('display_errors', value: 'stderr');

// The layout rules hold a token model of the file being linted, and a dense file of a few hundred KB
// outgrows PHP's default 128M. The worker is a process of its own, so give it room unless the
// caller set a bigger limit (or none).
$setting = (string) ini_get('memory_limit');
$limit = (int) $setting
* match (strtoupper(substr($setting, offset: -1))) {
    'G' => 1024 ** 3,
    'M' => 1024 ** 2,
    'K' => 1024,
    default => 1,
};
if ($limit !== -1 && $limit < (512 * 1024 * 1024)) {
    // @mago-expect lint:no-ini-set
    ini_set('memory_limit', value: '512M');
}

(static function (): void {
    $cwd = getcwd();
    $cwd = $cwd === false ? '.' : $cwd;
    // `--standard=WordPress-Extra` (the presets pass it) is the default composer.json can override.
    // The last one wins: Mago 1.47 appends a preset's command to the one it extends.
    $standard = 'WordPress';
    foreach ($_SERVER['argv'] ?? [] as $arg) {
        if (str_starts_with($arg, '--standard=')) {
            $standard = substr($arg, offset: 11);
        }
    }
    $candidates = [
        // The package is a dependency: vendor/rlorenzo/mago-wordpress/resources.
        dirname(__DIR__, levels: 3) . '/autoload.php',
        // The package is a symlinked path repository; Mago starts the worker in the project.
        $cwd . '/vendor/autoload.php',
        // The worker runs from a clone of this package.
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    foreach ($candidates as $autoloader) {
        if (!is_file($autoloader)) {
            continue;
        }

        require $autoloader;

        if (!class_exists(Worker::class) || !class_exists(WordPressExtension::class)) {
            continue;
        }

        try {
            $settings = SettingsDiscovery::in($cwd, $standard);
        } catch (\InvalidArgumentException $exception) {
            // Mago shows a worker's stderr when it exits; one readable line per invalid setting.
            $lines = explode("\n", $exception->getMessage());
            fwrite(
                STDERR,
                'mago-wordpress: invalid configuration: '
                . implode("\nmago-wordpress: invalid configuration: ", $lines)
                . "\n",
            );
            exit(1);
        }

        (new Worker(WordPressExtension::create($settings)))->run();

        return;
    }

    fwrite(STDERR, data: "mago-wordpress: could not locate the Composer autoloader.\n");
    exit(1);
})();
