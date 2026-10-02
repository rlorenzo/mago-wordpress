<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function bin2hex;
use function copy;
use function dirname;
use function escapeshellarg;
use function exec;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function getenv;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function ltrim;
use function mkdir;
use function passthru;
use function random_bytes;
use function realpath;
use function rtrim;
use function scandir;
use function sort;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function symlink;
use function sys_get_temp_dir;

use const ARRAY_FILTER_USE_BOTH;
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
        file would change; --staged takes the staged PHP files that your mago.toml lists (and re-stages
        them), so a staged file mago is not configured for is left alone.
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
            $relative = self::relative($path, $cwd);
            if ($relative === null) {
                fwrite(STDERR, "mago-wordpress format: {$path} is outside the project\n");

                return 2;
            }

            $paths[] = $relative;
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
     * A path as `mago list-files` prints it: relative to the project, without `./` or a trailing
     * slash. A path outside the project (absolute, or with a `..` segment) gives null.
     */
    private static function relative(string $path, string $cwd): ?string
    {
        $realCwd = realpath($cwd) ?: $cwd;
        $fullPath = str_starts_with($path, '/') ? $path : "{$cwd}/{$path}";
        $real = realpath($fullPath);
        if ($real !== false) {
            if ($real === $realCwd) {
                return '.';
            }

            if (!str_starts_with($real, "{$realCwd}/")) {
                return null;
            }

            return substr($real, strlen($realCwd) + 1);
        }

        if (str_starts_with($path, '/')) {
            $path = rtrim($path, characters: '/');
            if ($path === $cwd || $path === $realCwd) {
                return '.';
            }

            if (!str_starts_with($path, "{$cwd}/") && !str_starts_with($path, "{$realCwd}/")) {
                return null;
            }

            $prefix = str_starts_with($path, "{$realCwd}/") ? $realCwd : $cwd;
            $path = substr($path, strlen($prefix) + 1);
        }

        while (str_starts_with($path, './')) {
            $path = ltrim(substr($path, 2), characters: '/');
        }

        $path = rtrim($path, characters: '/');
        if (in_array('..', explode('/', $path), strict: true)) {
            return null;
        }

        return $path === '' ? '.' : $path;
    }

    /**
     * Runs the steps. `mago format` is not always stable (a comment after `<?php` before `endif;`
     * moves one place per run), so it is repeated until `mago format --check` passes. A file
     * whose formatting adds a lint finding is put back as it was (see keepFindings()).
     *
     * @param list<string> $paths
     */
    private static function steps(string $mago, string $cwd, array $paths, bool $capture): int
    {
        $files = self::files($mago, $cwd, $paths);
        if ($files === null) {
            return 1;
        }

        $before = [];
        foreach ($files as $file) {
            $before[$file] = (string) file_get_contents("{$cwd}/{$file}");
        }

        foreach (self::STEPS as $step) {
            $runs = $step === ['format'] ? 5 : 1; // ponytail: gives up after 5 runs; mago converged in 3 on bcap.
            for ($run = 1;; ++$run) {
                [$status, $output] = self::exec([$mago, ...$step, ...$paths], $cwd, $capture);
                if ($status !== 0) {
                    fwrite(STDERR, $output);

                    return $status;
                }

                if ($runs === 1) {
                    break;
                }

                // `mago format --check` exits 1 for files still unformatted and 2 for a tool error.
                [$check, $checkOutput] = self::exec([$mago, 'format', '--check', ...$paths], $cwd);
                if ($check === 0) {
                    break;
                }

                if ($check !== 1) {
                    fwrite(STDERR, $checkOutput);

                    return $check;
                }

                if ($run >= $runs) {
                    fwrite(STDERR, "mago format did not settle after {$runs} runs\n");

                    return 1;
                }
            }
        }

        return self::keepFindings($mago, $cwd, $before);
    }

    /**
     * Lints the files formatting changed, formatted and as they were, and puts back the original
     * of each one that formatting gives a finding it did not have: `mago format` can move a
     * translators comment away from its string (WordPress.WP.I18n.MissingTranslatorsComment) and
     * nothing else keeps it there.
     *
     * @param array<string, string> $before the files' contents before formatting
     */
    private static function keepFindings(string $mago, string $cwd, array $before): int
    {
        $formatted = [];
        foreach ($before as $file => $source) {
            $after = (string) file_get_contents("{$cwd}/{$file}");
            if ($after !== $source) {
                $formatted[$file] = $after;
            }
        }

        if ($formatted === []) {
            return 0;
        }

        $files = array_keys($formatted);
        $new = CommentConversion::lint($mago, $files, ignorePhpcs: false, cwd: $cwd);
        foreach ($files as $file) {
            file_put_contents("{$cwd}/{$file}", $before[$file]);
        }

        $old = CommentConversion::lint($mago, $files, ignorePhpcs: false, cwd: $cwd);
        if ($new === null || $old === null) {
            fwrite(STDERR, "mago lint did not return a JSON report; nothing was formatted.\n");

            return 1;
        }

        foreach ($formatted as $file => $source) {
            $had = self::rules($old[$file] ?? []);
            $added = array_filter(
                self::rules($new[$file] ?? []),
                static fn(int $count, string $rule): bool => $count > ($had[$rule] ?? 0),
                ARRAY_FILTER_USE_BOTH,
            );
            if ($added === []) {
                file_put_contents("{$cwd}/{$file}", $source);

                continue;
            }

            fwrite(
                STDERR,
                "{$file}: left unformatted: formatting adds "
                . implode(', ', array_keys($added))
                . " findings. Format it by hand, or keep it as it is.\n",
            );
        }

        return 0;
    }

    /**
     * @param list<array{int, string}> $issues
     * @return array<string, int> issues per rule
     */
    private static function rules(array $issues): array
    {
        $rules = [];
        foreach ($issues as [, $rule]) {
            $rules[$rule] = ($rules[$rule] ?? 0) + 1;
        }

        return $rules;
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

        // vendor/ is linked into the copy, so formatting a path under it would write to the real one.
        foreach ($paths as $path) {
            if ($path === 'vendor' || str_starts_with($path, 'vendor/')) {
                fwrite(STDERR, "--check does not format vendor/ ({$path}).\n");

                return 1;
            }
        }

        // Private (0700) and unpredictable: the copy holds the project's configuration and source.
        $temp = sys_get_temp_dir() . '/mago-wordpress-format-' . bin2hex(random_bytes(8));
        if (!mkdir($temp, 0o700)) {
            fwrite(STDERR, "Could not create the temporary directory {$temp}\n");

            return 1;
        }

        try {
            if (is_dir("{$cwd}/vendor") && !symlink("{$cwd}/vendor", "{$temp}/vendor")) {
                return self::setupFailed("link {$cwd}/vendor");
            }

            foreach ((array) scandir($cwd) as $entry) {
                if (is_file("{$cwd}/{$entry}") && !copy("{$cwd}/{$entry}", "{$temp}/{$entry}")) {
                    return self::setupFailed("copy {$entry}");
                }
            }

            foreach ($files as $file) {
                $directory = dirname("{$temp}/{$file}");
                if (!is_dir($directory) && !mkdir($directory, recursive: true)) {
                    return self::setupFailed("create {$directory}");
                }

                if (!copy("{$cwd}/{$file}", "{$temp}/{$file}")) {
                    return self::setupFailed("copy {$file}");
                }
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
                [$diffStatus, $diffOutput] = self::exec($command, $cwd);
                if ($diffStatus > 1) {
                    fwrite(STDERR, $diffOutput);

                    return $diffStatus;
                }

                fwrite(STDOUT, $diffOutput);
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

    private static function setupFailed(string $what): int
    {
        fwrite(STDERR, "mago-wordpress format --check: could not {$what} in the temporary project\n");

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
            '-z',
            '--name-only',
            '--relative',
            '--diff-filter=ACMR',
        ], $cwd);
        if ($files === null || $status !== 0) {
            fwrite(STDERR, $staged);

            return null;
        }

        // NUL-delimited: without -z git quotes names with non-ASCII bytes, and they would not match.
        $files = array_values(array_intersect($files, explode("\0", $staged)));
        if ($files === []) {
            return [];
        }

        [$partialStatus, $partial] = self::exec([
            'git',
            'diff',
            '-z',
            '--name-only',
            '--relative',
            '--',
            ...$files,
        ], $cwd);
        if ($partialStatus !== 0) {
            fwrite(STDERR, $partial);

            return null;
        }

        if ($partial !== '') {
            fwrite(
                STDERR,
                "Staged files also have unstaged changes; stage or stash them first:\n"
                . str_replace("\0", "\n", $partial)
                . "\n",
            );

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
