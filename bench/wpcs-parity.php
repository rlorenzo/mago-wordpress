<?php

/**
 * Runs WPCS's own sniff test files through the Mago rule that ports each sniff and
 * reports per-line agreement with the sniff's getErrorList()/getWarningList().
 *
 *   php bench/wpcs-parity.php /path/to/WordPress-Coding-Standards [--only=Sniff.Name] [--verbose]
 *       [--phpcs=/path/to/php_codesniffer/src/Standards]
 *
 * Non-WordPress sniffs are run against phpcs's own tests (`--phpcs`, default the global
 * Composer install) and totalled on a separate line.
 *
 * For every mapped sniff, each `<Sniff>UnitTest[.N].inc` is copied into a scratch Mago
 * workspace (keeping its name, so file-name checks see the original), the `phpcs:set`
 * directives and test-class CLI overrides that map to this package's settings are written to
 * the workspace's composer.json, and `mago lint --only <rules>` runs on it once per settings
 * state the file passes through; each line is judged by the state active at that line. An expected
 * line that some Mago report's primary span covers is a match; expected lines no span covers are
 * misses; reports whose span covers no expected line are extras. Error/warning levels are not compared: they differ by
 * design.
 *
 * Prints a markdown table (paste into bench/results/wpcs-parity.md); --verbose lists every
 * missed and extra line for triage.
 */

declare(strict_types=1);

namespace PHP_CodeSniffer\Tests\Standards {
    // Stands in for phpcs's test base so the WPCS test classes load without phpcs or PHPUnit.
    abstract class AbstractSniffUnitTest
    {
        public function setCliValues($filename, $config) {}
    }
}

namespace PHPCSUtils\BackCompat {
    final class Helper
    {
        public static function setConfigData(string $key, mixed $value, bool $temp, mixed $config): void {}
    }
}

namespace {
    use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
    use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
    use Rlorenzo\MagoWordPress\Internal\SniffMap;

    require dirname(__DIR__) . '/vendor/autoload.php';

    /** phpcs:set property => composer.json extra.mago-wordpress key. */
    define('SETTING_KEYS', array_map(static fn(array $setting): string => $setting[0], PhpcsRuleset::PROPERTY_SETTINGS) + [
        'minimum_wp_version' => 'minimum-wp-version',
        // Keyed by the sniff the directive names (settingsRegions()).
        'exclude' => 'exclude-groups',
        // NoSilencedErrors uses the PHP function list under WordPress-Core only (SNIFF_DEFAULTS).
        'usePHPFunctionsList' => 'standard',
    ]);

    /** Settings that reproduce a sniff's own property defaults where this package derives them from the standard. */
    const SNIFF_DEFAULTS = ['WordPress.PHP.NoSilencedErrors' => ['standard' => 'WordPress-Core']];

    /** What the test classes' setCliValues() set per file, mirrored here. */
    const CLI_OVERRIDES = [
        'CapabilitiesUnitTest.2.inc' => ['minimum-wp-version' => '2.9'],
        'CapabilitiesUnitTest.3.inc' => ['minimum-wp-version' => '6.1'],
        'I18nUnitTest.3.inc' => ['text-domains' => ['something']],
    ];

    const CORE_DISABLED = ['nonce-verification', 'validated-sanitized-input', 'prepared-sql'];

    /** Test files that depend on groups the test class injects into the sniff; no setting reproduces them. */
    const SKIP_FILES = ['RestrictedClassesUnitTest.2.inc', 'RestrictedClassesUnitTest.3.inc'];

    /**
     * Expected lines whose only message has a severity below phpcs's default of 5, which phpcs
     * (and so this package) does not report: ValidPostTypeSlug's NotStringLiteral (severity 3).
     */
    const LOW_SEVERITY_LINES = ['ValidPostTypeSlugUnitTest.1.inc' => [27, 28, 29, 30, 31, 33, 34, 67]];

    (static function (array $argv): void {
        $wpcs = null;
        $only = null;
        $verbose = false;
        // The global Composer install: $COMPOSER_HOME, else ~/.config/composer.
        $composerHome = getenv('COMPOSER_HOME') ?: (getenv('HOME') ? getenv('HOME') . '/.config/composer' : '');
        $phpcs = $composerHome === '' ? '' : "$composerHome/vendor/squizlabs/php_codesniffer/src/Standards";
        foreach (array_slice($argv, 1) as $arg) {
            if (str_starts_with($arg, '--only=')) {
                $only = substr($arg, 7);
            } elseif (str_starts_with($arg, '--phpcs=')) {
                $phpcs = rtrim(substr($arg, 8), '/');
            } elseif ($arg === '--verbose') {
                $verbose = true;
            } else {
                $wpcs = rtrim($arg, '/');
            }
        }

        if ($wpcs === null || !is_dir("$wpcs/WordPress/Tests")) {
            fwrite(STDERR, "usage: php bench/wpcs-parity.php /path/to/WordPress-Coding-Standards [--only=Sniff] [--verbose]\n");
            exit(1);
        }

        if ($phpcs === '' || !is_dir($phpcs)) {
            fwrite(STDERR, 'notice: phpcs standards not found' . ($phpcs === '' ? '' : " at $phpcs")
                . "; non-WordPress sniffs are listed as 'no upstream test'. Pass --phpcs=/path/to/php_codesniffer/src/Standards.\n");
        }

        $here = dirname(__DIR__);
        $mago = getenv('MAGO') ?: "$here/vendor/bin/mago";
        $work = sys_get_temp_dir() . '/wpcs-parity-' . getmypid();
        mkdir("$work/src", 0777, true);
        register_shutdown_function(static fn() => exec('rm -rf ' . escapeshellarg($work)));

        $rows = [];
        $totals = ['files' => 0, 'expected' => 0, 'matched' => 0, 'missed' => 0, 'extra' => 0];
        $genericTotals = $totals;
        foreach (SniffMap::RULES as $sniff => $rules) {
            if ($only !== null && $sniff !== $only) {
                continue;
            }

            [$standard, $category, $name] = explode('.', $sniff, 3);
            $class = "{$name}UnitTest";
            // Non-WordPress sniffs are tested by phpcs itself (PHPCSExtra ships no tests).
            [$dir, $fqcn] = $standard === 'WordPress'
                ? ["$wpcs/WordPress/Tests/$category", "WordPressCS\\WordPress\\Tests\\$category\\$class"]
                : ["$phpcs/$standard/Tests/$category", "PHP_CodeSniffer\\Standards\\$standard\\Tests\\$category\\$class"];
            if (!is_file("$dir/$class.php")) {
                $rows[] = [$sniff, '-', '-', '-', '-', '-', 'no upstream test'];
                continue;
            }

            require_once "$dir/$class.php";
            $test = new $fqcn();

            $files = glob("$dir/$class.inc") ?: [];
            $files = [...$files, ...(glob("$dir/$class.*.inc") ?: [])];
            $files = glob("$dir/{$class}s{/,/*/}*.{inc,php3}", GLOB_BRACE) ?: $files;
            $sum = ['files' => 0, 'expected' => 0, 'matched' => 0, 'missed' => 0, 'extra' => 0];
            $notes = [];
            foreach ($files as $path) {
                $base = basename($path);
                if (in_array($base, SKIP_FILES, true)) {
                    $notes[] = "$base skipped: test-only sniff groups";
                    continue;
                }

                $expected = array_diff_key(expectedLines($test, $base), array_flip(LOW_SEVERITY_LINES[$base] ?? []));
                $unmapped = [];
                $regions = settingsRegions($path, $sniff, $unmapped);
                if ($sniff === 'WordPress.Files.FileName') {
                    // The sniff runs once, at the first open tag, and reports on line 1: the
                    // directives before that tag apply to the whole file.
                    $before = strstr((string) file_get_contents($path), '<?php', true);
                    $open = $before === false ? 1 : substr_count($before, "\n") + 1;
                    $active = [];
                    foreach ($regions as $line => $state) {
                        if ($line <= $open) {
                            $active = $state;
                        }
                    }
                    $regions = [1 => $active];
                }
                if (count($regions) > 1) {
                    $notes[] = "$base: " . (count($regions) - 1) . ' phpcs:set directive(s), honoured by region';
                }

                if ($unmapped !== []) {
                    $notes[] = "$base: no setting for phpcs:set " . implode(', ', array_keys($unmapped));
                }

                // One lint per distinct settings state; each line is judged by the state active there.
                $runs = [];
                $spans = [];
                $starts = array_keys($regions);
                foreach ($starts as $position => $start) {
                    $settings = [...$regions[$start], ...(CLI_OVERRIDES[$base] ?? [])];
                    $state = json_encode($settings);
                    $runs[$state] ??= lintSpans($mago, $here, $work, $path, $rules, $settings);
                    $end = $starts[$position + 1] ?? PHP_INT_MAX;
                    foreach ($runs[$state] as [$from, $to]) {
                        if ($from >= $start && $from < $end) {
                            $spans[] = [$from, $to];
                        }
                    }
                }

                // WPCS anchors a report on the offending line inside a multi-line string or
                // comment; Mago anchors the node. An expected line anywhere inside a reported
                // span counts as matched, and a span that covers no expected line is an extra.
                $covers = static fn(array $span, int $line): bool => $line >= $span[0] && $line <= $span[1];
                $matched = [];
                foreach ($expected as $line => $_) {
                    foreach ($spans as $span) {
                        if ($covers($span, $line)) {
                            $matched[$line] = true;
                            break;
                        }
                    }
                }

                $missed = array_diff_key($expected, $matched);
                $extra = [];
                foreach ($spans as $span) {
                    $hit = false;
                    foreach ($expected as $line => $_) {
                        if ($covers($span, $line)) {
                            $hit = true;
                            break;
                        }
                    }

                    if (!$hit) {
                        $extra[$span[0]] = true;
                    }
                }

                ksort($extra);

                $sum['files']++;
                $sum['expected'] += count($expected);
                $sum['matched'] += count($matched);
                $sum['missed'] += count($missed);
                $sum['extra'] += count($extra);

                if ($verbose && ($missed !== [] || $extra !== [])) {
                    fwrite(STDERR, "\n$sniff / $base\n");
                    if ($missed !== []) {
                        fwrite(STDERR, '  missed: ' . implode(', ', array_keys($missed)) . "\n");
                    }
                    if ($extra !== []) {
                        fwrite(STDERR, '  extra:  ' . implode(', ', array_keys($extra)) . "\n");
                    }
                }
            }

            // The suite total stays WPCS's own tests; generic sniffs get their own line.
            foreach ($sum as $key => $value) {
                if ($standard === 'WordPress') {
                    $totals[$key] += $value;
                } else {
                    $genericTotals[$key] += $value;
                }
            }

            $recall = $sum['expected'] === 0 ? '-' : sprintf('%d%%', round(100 * $sum['matched'] / $sum['expected']));
            $rows[] = [
                $sniff,
                (string) $sum['files'],
                (string) $sum['expected'],
                (string) $sum['matched'],
                (string) $sum['missed'],
                (string) $sum['extra'],
                $recall,
                implode('; ', $notes),
            ];
        }

        echo "| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |\n";
        echo "|:---|---:|---:|---:|---:|---:|---:|:---|\n";
        foreach ($rows as $row) {
            if (count($row) === 7) {
                [$sniff, $a, $b, $c, $d, $e, $note] = $row;
                echo "| `$sniff` | $a | $b | $c | $d | $e | - | $note |\n";
                continue;
            }

            [$sniff, $files, $expected, $matched, $missed, $extra, $recall, $note] = $row;
            echo "| `$sniff` | $files | $expected | $matched | $missed | $extra | $recall | $note |\n";
        }

        foreach (['Total' => $totals, 'Generic sniffs (phpcs tests)' => $genericTotals] as $label => $sum) {
            $recall = $sum['expected'] === 0 ? '-' : sprintf('%d%%', round(100 * $sum['matched'] / $sum['expected']));
            echo "| **$label** | **{$sum['files']}** | **{$sum['expected']}** | **{$sum['matched']}** | **{$sum['missed']}** | **{$sum['extra']}** | **$recall** | |\n";
        }

        if (($GLOBALS['wpcsParityFailures'] ?? 0) > 0) {
            fwrite(STDERR, "{$GLOBALS['wpcsParityFailures']} file(s) produced no report; the table above under-counts them.\n");
            exit(2);
        }
    })($argv);

    /**
     * Lines WPCS expects at least one error or warning on, from the test class.
     *
     * @return array<int, true>
     */
    function expectedLines(AbstractSniffUnitTest $test, string $file): array
    {
        $lines = [];
        foreach (['getErrorList', 'getWarningList'] as $method) {
            $reflection = new ReflectionMethod($test, $method);
            $list = $reflection->getNumberOfParameters() > 0 ? $reflection->invoke($test, $file) : $reflection->invoke($test);
            foreach ($list as $line => $count) {
                if ($count > 0) {
                    $lines[(int) $line] = true;
                }
            }
        }

        ksort($lines);

        return $lines;
    }

    /**
     * The settings states a file passes through, from its phpcs:set directives for this sniff:
     * one entry per state, keyed by the first line it applies to. State 1 (before any directive)
     * has no settings. Directives are cumulative within the file, as in phpcs.
     *
     * @param array<string, true> $unmapped Receives phpcs:set properties this package has no setting for.
     * @return array<int, array<string, mixed>> line => settings active from that line on
     */
    function settingsRegions(string $path, string $sniff, array &$unmapped): array
    {
        $current = SNIFF_DEFAULTS[$sniff] ?? [];
        $regions = [1 => $current];
        foreach (explode("\n", (string) file_get_contents($path)) as $index => $text) {
            if (
                preg_match('/phpcs:set\s+(\S+)\s+([A-Za-z_]\w*)(\[\])?[ \t]*([^*\n]*?)\s*(?:\*\/)?\s*$/', $text, $match) !== 1
                || $match[1] !== $sniff
            ) {
                continue;
            }

            if (!isset(SETTING_KEYS[$match[2]])) {
                $unmapped[$match[2]] = true;
                continue;
            }

            [, , $property, $isList, $value] = $match;
            $key = SETTING_KEYS[$property];
            if ($key === 'standard') {
                $current[$key] = $value === 'false' ? 'WordPress' : 'WordPress-Core';
            } elseif ($key === 'exclude-groups') {
                $current[$key] = [$sniff => array_values(array_filter(explode(',', $value), 'strlen'))];
            } elseif ($isList !== '') {
                $current[$key] = array_values(array_filter(explode(',', $value), 'strlen'));
            } elseif ($value === '') {
                unset($current[$key]);
            } else {
                $current[$key] = $value;
            }

            // A directive takes effect on its own line; phpcs applies it before scanning further.
            $regions[$index + 1] = $current;
        }

        return $regions;
    }

    /**
     * Primary spans (first to last line) Mago reports when linting the file with the given
     * rules in a scratch workspace.
     *
     * @param list<string> $rules
     * @param array<string, mixed> $settings
     * @return list<array{int, int}>
     */
    function lintSpans(string $mago, string $here, string $work, string $path, array $rules, array $settings): array
    {
        exec('rm -rf ' . escapeshellarg("$work/src"));
        $relative = preg_match('`UnitTests/(.+)$`', $path, $m) === 1 ? $m[1] : basename($path);
        mkdir(dirname("$work/src/$relative"), 0777, true);
        copy($path, "$work/src/$relative");

        $enabled = implode("\n", array_map(
            static fn(string $rule): string => "$rule = { enabled = true }",
            array_intersect(CORE_DISABLED, $rules),
        ));
        $json = static fn(mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents("$work/mago.toml", <<<TOML
            php-version = "8.1"
            [source]
            paths = ["src"]
            extensions = ["inc", "php3"]
            [linter]
            integrations = ["wordpress"]
            [linter.rules]
            $enabled
            [extension-hosts.wordpress]
            command = ["php", {$json("$here/resources/worker.php")}]

            TOML);
        file_put_contents("$work/composer.json", $json(['extra' => ['mago-wordpress' => (object) $settings]]));

        $command = sprintf(
            'cd %s && %s --workspace %s lint --only %s --reporting-format json 2>/dev/null',
            escapeshellarg($work),
            escapeshellarg($mago),
            escapeshellarg($work),
            escapeshellarg(implode(',', $rules)),
        );
        exec($command, $output, $status);
        $report = json_decode(implode("\n", $output), true);
        if (!is_array($report)) {
            // A crashed or misconfigured Mago would otherwise read as "everything missed".
            fwrite(STDERR, "error: no JSON report for $path (mago exit $status)\n");
            $GLOBALS['wpcsParityFailures'] = ($GLOBALS['wpcsParityFailures'] ?? 0) + 1;

            return [];
        }

        // Mago also reports parser and semantics errors regardless of --only; only the rules count.
        $spans = [];
        foreach ($report['issues'] ?? [] as $issue) {
            if (!in_array($issue['code'] ?? '', $rules, true)) {
                continue;
            }

            foreach ($issue['annotations'] ?? [] as $annotation) {
                if (($annotation['kind'] ?? '') === 'Primary') {
                    // Mago's JSON report counts lines from 0.
                    $spans[] = [(int) $annotation['span']['start']['line'] + 1, (int) $annotation['span']['end']['line'] + 1];
                    break;
                }
            }
        }

        return $spans;
    }
}
