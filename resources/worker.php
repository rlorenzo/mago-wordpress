<?php

/**
 * Ready-made worker entrypoint, so one TOML block adds this extension:
 *
 *     [extension-hosts.wordpress]
 *     command = ["php", "vendor/rlorenzo/mago-wordpress/resources/worker.php"]
 *
 * Settings (text domains, prefixes, minimum WordPress version, custom escaping
 * functions) are read from the project's composer.json `extra.mago-wordpress`,
 * falling back to its phpcs.xml properties.
 */

declare(strict_types=1);

use Mago\Sdk\Worker;
use Rlorenzo\MagoWordPress\Internal\SettingsDiscovery;
use Rlorenzo\MagoWordPress\WordPressExtension;

(static function (): void {
    $cwd = getcwd();
    $cwd = $cwd === false ? '.' : $cwd;
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

        (new Worker(WordPressExtension::create(SettingsDiscovery::in($cwd))))->run();

        return;
    }

    fwrite(STDERR, data: "mago-wordpress: could not locate the Composer autoloader.\n");
    exit(1);
})();
