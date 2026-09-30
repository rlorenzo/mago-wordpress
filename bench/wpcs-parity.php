<?php

/**
 * Runs WPCS's own sniff test files through the Mago rule that ports each sniff and
 * reports per-line agreement with the sniff's getErrorList()/getWarningList().
 *
 *   php bench/wpcs-parity.php /path/to/WordPress-Coding-Standards [--only=Sniff.Name] [--verbose]
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

    /**
     * Sniff => Mago rule codes. 'core' rules need the wordpress integration; the three that
     * Mago ships disabled are enabled in the scratch workspace.
     */
    const SNIFF_RULES = [
        'WordPress.CodeAnalysis.AssignmentInTernaryCondition' => ['wordpress/assignment-in-ternary-condition'],
        'WordPress.CodeAnalysis.EscapedNotTranslated' => ['wordpress/escaped-not-translated'],
        'WordPress.DateTime.CurrentTimeTimestamp' => ['wordpress/wp-date-time'],
        'WordPress.DateTime.RestrictedFunctions' => ['wordpress/wp-date-time'],
        'WordPress.DB.DirectDatabaseQuery' => ['no-direct-db-query', 'no-db-schema-change'],
        'WordPress.DB.PreparedSQL' => ['prepared-sql'],
        'WordPress.DB.PreparedSQLPlaceholders' => [
            'wordpress/prepared-sql-placeholders',
            'wordpress/prepared-sql-unquoted-complex-placeholder',
        ],
        'WordPress.DB.RestrictedClasses' => ['wordpress/db-restricted-classes'],
        'WordPress.DB.RestrictedFunctions' => ['wordpress/db-restricted-functions'],
        'WordPress.DB.SlowDBQuery' => ['wordpress/slow-db-query'],
        'WordPress.Files.FileName' => ['wordpress/file-name'],
        'WordPress.NamingConventions.PrefixAllGlobals' => ['wordpress/prefix-all-globals'],
        'WordPress.NamingConventions.ValidFunctionName' => ['wordpress/valid-function-name'],
        'WordPress.NamingConventions.ValidHookName' => ['wordpress/valid-hook-name'],
        'WordPress.NamingConventions.ValidPostTypeSlug' => ['wordpress/valid-post-type-slug'],
        'WordPress.NamingConventions.ValidVariableName' => ['wordpress/valid-variable-name'],
        'WordPress.PHP.DevelopmentFunctions' => ['wordpress/discouraged-wp-functions', 'no-debug-symbols'],
        'WordPress.PHP.DiscouragedPHPFunctions' => ['wordpress/discouraged-wp-functions'],
        'WordPress.PHP.DontExtract' => ['wordpress/dont-extract'],
        'WordPress.PHP.IniSet' => ['no-ini-set'],
        'WordPress.PHP.NoSilencedErrors' => ['no-error-control-operator'],
        'WordPress.PHP.PregQuoteDelimiter' => ['require-preg-quote-delimiter'],
        'WordPress.PHP.RestrictedPHPFunctions' => ['wordpress/restricted-php-functions'],
        'WordPress.PHP.StrictInArray' => ['wordpress/strict-in-array'],
        'WordPress.PHP.TypeCasts' => ['wordpress/type-casts'],
        'WordPress.PHP.YodaConditions' => ['wordpress/yoda-conditions'],
        'WordPress.Security.EscapeOutput' => ['no-unescaped-output'],
        'WordPress.Security.NonceVerification' => ['nonce-verification'],
        'WordPress.Security.PluginMenuSlug' => ['wordpress/plugin-menu-slug'],
        'WordPress.Security.SafeRedirect' => ['wordpress/safe-redirect'],
        'WordPress.Security.ValidatedSanitizedInput' => ['validated-sanitized-input'],
        'WordPress.WP.AlternativeFunctions' => ['use-wp-functions'],
        'WordPress.WP.Capabilities' => ['wordpress/capabilities', 'no-roles-as-capabilities'],
        'WordPress.WP.CapitalPDangit' => ['wordpress/capital-p-dangit'],
        'WordPress.WP.ClassNameCase' => ['wordpress/class-name-case'],
        'WordPress.WP.CronInterval' => ['wordpress/cron-interval'],
        'WordPress.WP.DeprecatedClasses' => ['wordpress/wp-deprecated-classes'],
        'WordPress.WP.DeprecatedFunctions' => ['wordpress/wp-deprecated-functions'],
        'WordPress.WP.DeprecatedParameters' => ['wordpress/wp-deprecated-parameters'],
        'WordPress.WP.DeprecatedParameterValues' => ['wordpress/wp-deprecated-parameter-values'],
        'WordPress.WP.DiscouragedConstants' => ['wordpress/discouraged-constants'],
        'WordPress.WP.DiscouragedFunctions' => ['wordpress/discouraged-wp-functions'],
        'WordPress.WP.EnqueuedResourceParameters' => ['wordpress/enqueued-resource-parameters'],
        'WordPress.WP.EnqueuedResources' => ['wordpress/enqueued-resources'],
        'WordPress.WP.GetMetaSingle' => ['wordpress/get-meta-single'],
        'WordPress.WP.GlobalVariablesOverride' => ['wordpress/global-variables-override'],
        'WordPress.WP.I18n' => ['wordpress/wp-i18n'],
        'WordPress.WP.PostsPerPage' => ['wordpress/posts-per-page'],
    ];

    /** phpcs:set property => composer.json extra.mago-wordpress key. */
    const SETTING_KEYS = [
        'text_domain' => 'text-domains',
        'prefixes' => 'prefixes',
        'minimum_wp_version' => 'minimum-wp-version',
        'custom_capabilities' => 'custom-capabilities',
        'posts_per_page' => 'max-posts-per-page',
        'min_interval' => 'min-cron-interval',
        'additional_word_delimiters' => 'additional-word-delimiters',
        'customEscapingFunctions' => 'custom-escaping-functions',
        'customAutoEscapedFunctions' => 'custom-auto-escaped-functions',
        'customSanitizingFunctions' => 'custom-sanitizing-functions',
        'customUnslashingSanitizingFunctions' => 'custom-unslashing-sanitizing-functions',
    ];

    /** What the test classes' setCliValues() set per file, mirrored here. */
    const CLI_OVERRIDES = [
        'CapabilitiesUnitTest.2.inc' => ['minimum-wp-version' => '2.9'],
        'CapabilitiesUnitTest.3.inc' => ['minimum-wp-version' => '6.1'],
        'I18nUnitTest.3.inc' => ['text-domains' => ['something']],
    ];

    const CORE_DISABLED = ['nonce-verification', 'validated-sanitized-input', 'prepared-sql'];

    /** Test files that depend on groups the test class injects into the sniff; no setting reproduces them. */
    const SKIP_FILES = ['RestrictedClassesUnitTest.2.inc', 'RestrictedClassesUnitTest.3.inc'];

    (static function (array $argv): void {
        $wpcs = null;
        $only = null;
        $verbose = false;
        foreach (array_slice($argv, 1) as $arg) {
            if (str_starts_with($arg, '--only=')) {
                $only = substr($arg, 7);
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

        $here = dirname(__DIR__);
        $mago = getenv('MAGO') ?: "$here/vendor/bin/mago";
        $work = sys_get_temp_dir() . '/wpcs-parity-' . getmypid();
        mkdir("$work/src", 0777, true);
        register_shutdown_function(static fn() => exec('rm -rf ' . escapeshellarg($work)));

        $rows = [];
        $totals = ['files' => 0, 'expected' => 0, 'matched' => 0, 'missed' => 0, 'extra' => 0];
        foreach (SNIFF_RULES as $sniff => $rules) {
            if ($only !== null && $sniff !== $only) {
                continue;
            }

            [, $category, $name] = explode('.', $sniff, 3);
            $dir = "$wpcs/WordPress/Tests/$category";
            $class = "{$name}UnitTest";
            if (!is_file("$dir/$class.php")) {
                $rows[] = [$sniff, '-', '-', '-', '-', '-', 'no WPCS test'];
                continue;
            }

            require_once "$dir/$class.php";
            $fqcn = "WordPressCS\\WordPress\\Tests\\$category\\$class";
            $test = new $fqcn();

            $files = glob("$dir/$class.inc") ?: [];
            $files = [...$files, ...(glob("$dir/$class.*.inc") ?: [])];
            $sum = ['files' => 0, 'expected' => 0, 'matched' => 0, 'missed' => 0, 'extra' => 0];
            $notes = [];
            foreach ($files as $path) {
                $base = basename($path);
                if (in_array($base, SKIP_FILES, true)) {
                    $notes[] = "$base skipped: test-only sniff groups";
                    continue;
                }

                $expected = expectedLines($test, $base);
                $regions = settingsRegions($path, $sniff);
                if (count($regions) > 1) {
                    $notes[] = "$base: " . (count($regions) - 1) . ' phpcs:set directive(s), honoured by region';
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

            foreach ($sum as $key => $value) {
                $totals[$key] += $value;
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

        $recall = $totals['expected'] === 0 ? '-' : sprintf('%d%%', round(100 * $totals['matched'] / $totals['expected']));
        echo "| **Total** | **{$totals['files']}** | **{$totals['expected']}** | **{$totals['matched']}** | **{$totals['missed']}** | **{$totals['extra']}** | **$recall** | |\n";
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
     * @return array<int, array<string, mixed>> line => settings active from that line on
     */
    function settingsRegions(string $path, string $sniff): array
    {
        $regions = [1 => []];
        $current = [];
        foreach (explode("\n", (string) file_get_contents($path)) as $index => $text) {
            if (
                preg_match('/phpcs:set\s+(\S+)\s+([A-Za-z_]\w*)(\[\])?[ \t]*([^*\n]*?)\s*(?:\*\/)?\s*$/', $text, $match) !== 1
                || $match[1] !== $sniff
                || !isset(SETTING_KEYS[$match[2]])
            ) {
                continue;
            }

            [, , $property, $isList, $value] = $match;
            $key = SETTING_KEYS[$property];
            if ($isList !== '') {
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
        array_map('unlink', glob("$work/src/*") ?: []);
        copy($path, "$work/src/" . basename($path));

        $enabled = implode("\n", array_map(
            static fn(string $rule): string => "$rule = { enabled = true }",
            array_intersect(CORE_DISABLED, $rules),
        ));
        $json = static fn(mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents("$work/mago.toml", <<<TOML
            php-version = "8.1"
            [source]
            paths = ["src"]
            extensions = ["inc"]
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
        exec($command, $output);
        $report = json_decode(implode("\n", $output), true);
        if (!is_array($report)) {
            fwrite(STDERR, "warning: no JSON report for $path\n");

            return [];
        }

        $spans = [];
        foreach ($report['issues'] ?? [] as $issue) {
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
