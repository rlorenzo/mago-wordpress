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

        (new Worker(WordPressExtension::create(SettingsDiscovery::in($cwd, $standard))))->run();

        return;
    }

    fwrite(STDERR, data: "mago-wordpress: could not locate the Composer autoloader.\n");
    exit(1);
})();
