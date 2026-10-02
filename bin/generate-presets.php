<?php

/**
 * Regenerates the Mago core rule switches in wordpress.mago.toml and the wordpress-core and
 * wordpress-extra presets.
 *
 *   php bin/generate-presets.php [--check]
 *
 * A preset reports what its WPCS standard reports, so a Mago core rule that is on by default
 * stays on only when `SniffMap::RULES` maps a sniff of that standard to it; every other one is
 * turned off. The default-on rules come from the installed Mago (`mago lint --list-rules`), the
 * standard of each sniff from `PhpcsRuleset::excludedSniffs()`. wordpress.mago.toml is the full
 * `WordPress` standard; the two presets extend it and turn off what Core or Extra leave out, and
 * pass `--standard=` to the worker so its rules drop those sniffs too. Only the region between
 * the BEGIN/END markers of wordpress.mago.toml is rewritten; the rest (the formatter settings)
 * is kept as is and inherited by the presets.
 *
 * A rule key Mago does not know is a config error, so a new default-on rule in a later Mago
 * cannot be turned off here without raising the lowest supported Mago. `--check` writes
 * nothing and exits 1 when a file is out of date (`just check` runs it).
 */

declare(strict_types=1);

use Rlorenzo\MagoWordPress\Internal\PhpcsRuleset;
use Rlorenzo\MagoWordPress\Internal\SniffMap;
use Rlorenzo\MagoWordPress\Internal\WordPress\Levels;

require __DIR__ . '/../vendor/autoload.php';

$check = in_array('--check', array_slice($argv, 1), true);
$root = dirname(__DIR__);

// The rules a project with no configuration gets, at Mago's default (latest) PHP version.
$workspace = sys_get_temp_dir() . '/mago-wordpress-presets-' . getmypid();
@mkdir($workspace);
file_put_contents("{$workspace}/mago.toml", "version = \"1\"\n");
$json = shell_exec(
    escapeshellarg("{$root}/vendor/bin/mago") . ' --workspace ' . escapeshellarg($workspace)
    . ' --config ' . escapeshellarg("{$workspace}/mago.toml") . ' lint --list-rules --json 2>/dev/null',
);
unlink("{$workspace}/mago.toml");
rmdir($workspace);
$listed = is_string($json) ? json_decode($json, true) : null;
if (!is_array($listed) || $listed === []) {
    fwrite(STDERR, "could not read `mago lint --list-rules --json`\n");
    exit(2);
}
$defaultOn = array_column($listed, 'code');
sort($defaultOn);
$defaultLevel = array_map(strtolower(...), array_column($listed, 'level', 'code'));

/**
 * Core rules SniffMap maps to a sniff but that report code the sniff accepts, measured on the
 * bake-off plugins and bcap_website (phpcs-clean): off in every preset.
 */
const DIVERGENT = [
    // PSR2.Files.ClosingTag skips any file with inline HTML; Mago's no-closing-tag flags a
    // template that ends in a closing tag (19 on bcap_website, none from phpcs).
    'no-closing-tag',
    // PEAR.NamingConventions.ValidClassName accepts WordPress's `Foo_Bar`; Mago's class-name
    // wants PascalCase (1,216 reports on wordpress-seo, 10 on akismet, none from phpcs).
    'class-name',
];

/**
 * Kept core rules over a sniff that reports some codes as errors and others as warnings, at the
 * level of the codes the rule matches most.
 */
const MIXED = [
    // Mago reports every ini_set(); IniSet reports most as Risky (a warning), a few as Disallowed.
    'no-ini-set' => 'warning',
];

/** Options of a generated rule line besides its level. */
const OPTIONS = [
    // WordPress-Core requires long arrays (Universal.Arrays.DisallowShortArraySyntax); Mago's
    // default is the opposite. `mago lint --fix --only array-style` converts `[]` to `array()`.
    'array-style' => ['style = "long"'],
];

/**
 * Core security rules no WPCS sniff runs that stay on in every preset: they make code safer and,
 * measured on the 10 bake-off plugins, wordpress-develop and one private site, add almost no
 * reports. (Off for being noisy: no-request-variable, 1,380 reports that duplicate
 * wordpress/validated-sanitized-input; no-literal-password and no-insecure-comparison, mostly
 * false positives on option names and variables named token or key.)
 */
const SECURITY = [
    'tainted-data-to-sink', // 0 reports
    'no-unsafe-finally', // 1 report
    'no-variable-variable', // 1 report
    'no-ffi', // 0 reports
];

/** The core rules that port a sniff of the standard, and the SECURITY ones. */
$kept = static function (string $standard): array {
    $excluded = PhpcsRuleset::excludedSniffs($standard);
    $rules = SECURITY;
    foreach (SniffMap::RULES as $sniff => $codes) {
        if (in_array($sniff, $excluded, true)) {
            continue;
        }
        foreach ($codes as $code) {
            if (!SniffMap::isExtensionRule($code) && !in_array($code, DIVERGENT, true)) {
                $rules[] = $code;
            }
        }
    }

    return $rules;
};

$disabled = static fn(array $rules): string => implode('', array_map(
    static fn(string $rule): string => "{$rule} = { enabled = false }\n",
    $rules,
));

$full = array_values(array_diff($defaultOn, $kept('WordPress')));
$files = [];

/*
 * The core rules that port a sniff report at its phpcs level (`Levels`, generated from the WPCS
 * source) where that differs from Mago's default. A sniff that decides at runtime
 * (`error|warning`) counts as an error: those (ForbiddenFunctions, UnnecessaryStringConcat)
 * default to errors. The presets extend wordpress.mago.toml, so they inherit these.
 */
$leveled = '';
foreach (array_intersect($defaultOn, $kept('WordPress')) as $rule) {
    $found = [];
    foreach (SniffMap::RULES as $sniff => $codes) {
        if (in_array($rule, $codes, true)) {
            $found = [...$found, ...array_values(Levels::SNIFFS[$sniff] ?? [])];
        }
    }
    $level = MIXED[$rule] ?? (array_diff($found, ['warning']) !== [] ? 'error' : 'warning');
    $options = OPTIONS[$rule] ?? [];
    if ($found !== [] && $level !== $defaultLevel[$rule]) {
        $options[] = "level = \"{$level}\"";
    }
    if ($options !== []) {
        $leveled .= "{$rule} = { " . implode(', ', $options) . " }\n";
    }
}

$base = (string) file_get_contents("{$root}/wordpress.mago.toml");
$begin = "# BEGIN generated by bin/generate-presets.php: Mago core rules that report what no WPCS sniff does\n"
    . "# are off, apart from four quiet security rules (tainted-data-to-sink, no-unsafe-finally,\n"
    . "# no-variable-variable, no-ffi). class-name is off because it reports code\n"
    . "# PEAR ValidClassName accepts. The ones that port a sniff report at its phpcs level, and\n"
    . "# array-style is `long` as WordPress-Core requires (`mago lint --fix --only array-style` converts).\n";
$end = "# END generated\n";
$from = strpos($base, '# BEGIN generated by bin/generate-presets.php');
$to = strpos($base, $end);
if ($from === false || $to === false || $to < $from) {
    fwrite(STDERR, "wordpress.mago.toml has no generated region\n");
    exit(2);
}
$files['wordpress.mago.toml'] = substr($base, 0, $from) . $begin . $disabled($full) . $leveled . substr($base, $to);

foreach (['WordPress-Core' => 'wordpress-core', 'WordPress-Extra' => 'wordpress-extra'] as $standard => $name) {
    $off = array_values(array_diff($defaultOn, $full, $kept($standard)));
    $files["{$name}.mago.toml"] = <<<TOML
        # Generated by bin/generate-presets.php; do not edit.
        #
        # The {$standard} standard: extend this file instead of wordpress.mago.toml when your
        # phpcs.xml was built on <rule ref="{$standard}"/>.
        #
        #     extends = "vendor/rlorenzo/mago-wordpress/{$name}.mago.toml"
        #
        # It turns off what {$standard} does not run: the wordpress/* rules for sniffs outside it
        # (the --standard argument below; composer.json `extra.mago-wordpress.standard` overrides it),
        # and the Mago core rules below, which port sniffs outside it.
        extends = "wordpress.mago.toml"

        [linter.rules]
        # WordPress-Docs (missing docblocks) is part of the full WordPress standard only.
        missing-docs = { enabled = false }

        TOML . $disabled($off) . <<<TOML

        [extension-hosts.wordpress]
        command = ["php", "vendor/rlorenzo/mago-wordpress/resources/worker.php", "--standard={$standard}"]

        TOML;
}

$stale = 0;
foreach ($files as $file => $contents) {
    $path = "{$root}/{$file}";
    if (is_file($path) && file_get_contents($path) === $contents) {
        continue;
    }
    if ($check) {
        fwrite(STDERR, "{$file} is out of date; run php bin/generate-presets.php\n");
        $stale = 1;
    } else {
        file_put_contents($path, $contents);
        echo "wrote {$file}\n";
    }
}
exit($stale);
