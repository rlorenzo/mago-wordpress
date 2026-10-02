<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function copy;
use function dirname;
use function escapeshellarg;
use function exec;
use function explode;
use function file_get_contents;
use function fwrite;
use function getenv;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function mkdir;
use function passthru;
use function rtrim;
use function scandir;
use function sort;
use function str_starts_with;
use function strlen;
use function substr;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;

use const STDERR;
use const STDOUT;

/**
 * `mago-wordpress format [paths] [--check] [--staged]`: the three steps that make WordPress
 * formatting (long arrays, `mago format`, the spaces inside parentheses `mago format` removes), in
 * order, as one command. `--check` runs them on a temporary copy of the project and fails with a
 * unified diff if anything would change; `--staged` limits both to the PHP files staged in git.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FormatCommand
{
    private const STEPS = [
        ['lint',   '--fix', '--only', 'array-style'],
        ['format'],
        ['lint',   '--fix', '--only', 'wordpress/parentheses-spacing'],
    ];

    public const USAGE = <<<'USAGE'
        Usage: mago-wordpress format [<paths>...] [--check] [--staged]

        Formats PHP files for WordPress: `mago lint --fix --only array-style`, `mago format`,
        then `mago lint --fix --only wordpress/parentheses-spacing`. Without paths it formats
        the files your mago.toml lists. --check changes nothing and fails with a diff if any
        file would change; --staged takes only the PHP files staged in git (and re-stages them).
        Set MAGO to use a mago binary other than vendor/bin/mago.

        USAGE;

    /**
     * @param list<string> $args
     */
    public static function run(array $args, string $cwd): int
    {
        $flags = array_values(array_filter($args, static fn(string $arg): bool => str_starts_with($arg, '--')));
        if (array_diff($flags, ['--check', '--staged']) !== []) {
            $help = $flags === ['--help'];
            fwrite($help ? STDOUT : STDERR, self::USAGE);

            return $help ? 0 : 2;
        }

        $check = in_array('--check', $flags, strict: true);
        $mago = (string) getenv('MAGO');
        $mago = $mago === '' ? "{$cwd}/vendor/bin/mago" : $mago;
        $paths = [];
        foreach (array_diff($args, $flags) as $path) {
            $path = rtrim($path, characters: '/');
            $paths[] = str_starts_with($path, "{$cwd}/") ? substr($path, strlen($cwd) + 1) : $path;
        }

        if (in_array('--staged', $flags, strict: true)) {
            $paths = self::staged($mago, $cwd, $paths);
            if ($paths === null) {
                return 1;
            }

            if ($paths === []) {
                return 0;
            }
        }

        if (!$check) {
            $status = self::steps($mago, $cwd, $paths, capture: false);
            if ($status !== 0) {
                return $status;
            }

            if (in_array('--staged', $flags, strict: true)) {
                return self::exec(['git', 'add', '--', ...$paths], $cwd, capture: false)[0];
            }

            return 0;
        }

        return self::check($mago, $cwd, $paths);
    }

    /**
     * Runs the steps. `mago format` is not always stable (a comment after `<?php` before `endif;`
     * moves one place per run), so it is repeated until `mago format --check` passes.
     *
     * @param list<string> $paths
     */
    private static function steps(string $mago, string $cwd, array $paths, bool $capture): int
    {
        foreach (self::STEPS as $step) {
            $runs = $step === ['format'] ? 5 : 1; // ponytail: gives up after 5 runs; mago converged in 3 on bcap.
            do {
                [$status, $output] = self::exec([$mago, ...$step, ...$paths], $cwd, $capture);
                if ($status !== 0) {
                    fwrite(STDERR, $output);

                    return $status;
                }
            } while (--$runs > 0 && self::exec([$mago, 'format', '--check', ...$paths], $cwd)[0] !== 0);
        }

        return 0;
    }

    /**
     * Copies the files to a temporary project (top-level files such as mago.toml and composer.json
     * copied, vendor/ linked, so configuration resolves as it does here), formats the copy, and
     * prints a diff for every file that changed.
     *
     * @param list<string> $paths
     */
    private static function check(string $mago, string $cwd, array $paths): int
    {
        $files = self::files($mago, $cwd, $paths);
        if ($files === null) {
            return 1;
        }

        $temp = sys_get_temp_dir() . '/' . uniqid('mago-wordpress-format-', more_entropy: true);
        mkdir($temp);
        try {
            if (is_dir("{$cwd}/vendor")) {
                symlink("{$cwd}/vendor", "{$temp}/vendor");
            }

            foreach ((array) scandir($cwd) as $entry) {
                if (is_file("{$cwd}/{$entry}")) {
                    copy("{$cwd}/{$entry}", "{$temp}/{$entry}");
                }
            }

            foreach ($files as $file) {
                if (!is_dir(dirname("{$temp}/{$file}"))) {
                    mkdir(dirname("{$temp}/{$file}"), recursive: true);
                }

                copy("{$cwd}/{$file}", "{$temp}/{$file}");
            }

            $status = self::steps($mago, $temp, $paths, capture: true);
            if ($status !== 0) {
                return $status;
            }

            $changed = 0;
            foreach ($files as $file) {
                if (file_get_contents("{$cwd}/{$file}") === file_get_contents("{$temp}/{$file}")) {
                    continue;
                }

                ++$changed;
                $command = [
                    'diff',
                    '-u',
                    '--label',
                    "a/{$file}",
                    '--label',
                    "b/{$file}",
                    "{$cwd}/{$file}",
                    "{$temp}/{$file}",
                ];
                fwrite(STDOUT, self::exec($command, $cwd)[1]);
            }
        } finally {
            self::exec(['rm', '-rf', $temp], $cwd);
        }

        if ($changed === 0) {
            return 0;
        }

        fwrite(STDERR, "{$changed} file(s) not formatted; run vendor/bin/mago-wordpress format.\n");

        return 1;
    }

    /**
     * The files mago lints or formats, limited to `$paths` when there are any.
     *
     * @param list<string> $paths
     * @return list<string>|null
     */
    private static function files(string $mago, string $cwd, array $paths): ?array
    {
        $files = [];
        foreach (['linter', 'formatter'] as $command) {
            [$status, $output] = self::exec([$mago, 'list-files', '--command', $command], $cwd);
            if ($status !== 0) {
                fwrite(STDERR, $output);

                return null;
            }

            $files = array_merge($files, array_filter(explode("\n", $output)));
        }

        $files = array_values(array_unique($files));
        if ($paths !== []) {
            $files = array_values(array_filter($files, static function (string $file) use ($paths): bool {
                foreach ($paths as $path) {
                    if ($path === '.' || $file === $path || str_starts_with($file, "{$path}/")) {
                        return true;
                    }
                }

                return false;
            }));
            foreach ($paths as $path) {
                if (!is_file("{$cwd}/{$path}") || in_array($path, $files, strict: true)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Staged PHP files that mago lints or formats. A file with unstaged changes too is refused,
     * since formatting it and re-staging it would stage those changes.
     *
     * @param list<string> $paths
     * @return list<string>|null
     */
    private static function staged(string $mago, string $cwd, array $paths): ?array
    {
        $files = self::files($mago, $cwd, $paths);
        [$status, $staged] = self::exec([
            'git',
            'diff',
            '--cached',
            '--name-only',
            '--relative',
            '--diff-filter=ACMR',
        ], $cwd);
        if ($files === null || $status !== 0) {
            fwrite(STDERR, $staged);

            return null;
        }

        $files = array_values(array_intersect($files, explode("\n", $staged)));
        if ($files === []) {
            return [];
        }

        $partial = self::exec(['git', 'diff', '--name-only', '--relative', '--', ...$files], $cwd)[1];
        if ($partial !== '') {
            fwrite(STDERR, "Staged files also have unstaged changes; stage or stash them first:\n{$partial}");

            return null;
        }

        return $files;
    }

    /**
     * Runs a command in `$cwd`, its output passed through, or captured (stdout and stderr together).
     *
     * @param list<string> $command
     * @return array{int, string} exit status and captured output
     */
    private static function exec(array $command, string $cwd, bool $capture = true): array
    {
        $line = 'cd ' . escapeshellarg($cwd) . ' && ' . implode(' ', array_map(escapeshellarg(...), $command));
        $status = 0;
        if (!$capture) {
            passthru($line, $status);

            return [$status, ''];
        }

        $output = [];
        exec("{$line} 2>&1", $output, $status);

        return [$status, $output === [] ? '' : implode("\n", $output) . "\n"];
    }
}
