<?php

/**
 * Compares, rule by rule, the lines phpcs and this package report on the same code.
 *
 *   phpcs --standard=<ruleset> --extensions=php --report=json -q <dir> > phpcs.json
 *   mago lint --ignore-baseline --minimum-fail-level note --reporting-format json > mago.json
 *   php bench/line-parity.php phpcs.json mago.json <dir>
 *
 * Sniffs that share one rule (SniffMap) are compared together: the rule's lines against the union of
 * its sniffs' lines. Only sniffs that SniffMap maps to a rule are compared, so the formatting sniffs
 * `mago format` covers and the `missing-docs` rule (WordPress-Docs) stay out, and so do the message
 * codes of a partly ported sniff that no rule reports. Prints the rules whose
 * (file, line) sets differ and the totals; exit status 1 if any differ.
 */

declare(strict_types=1);

use Rlorenzo\MagoWordPress\Internal\SniffMap;

require __DIR__ . '/../vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "usage: php bench/line-parity.php <phpcs.json> <mago.json> <project-dir>\n");
    exit(2);
}

$phpcs = json_decode((string) file_get_contents($argv[1]), true);
$mago = json_decode((string) file_get_contents($argv[2]), true);
$root = rtrim((string) realpath($argv[3]), '/') . '/';

/**
 * Sniffs a rule ports only in part: the message codes it reports (the rest is layout `mago format`
 * produces). Other codes of these sniffs are left out of the comparison.
 *
 * @var array<string, list<string>>
 */
$partial = [
    'Squiz.ControlStructures.ControlSignature' => ['SpaceAfterCloseBrace'],
    'PSR2.ControlStructures.SwitchDeclaration' => ['TerminatingComment'],
];

/** @var array<string, list<string>> $groups rule codes joined by "+" => the sniffs they port */
$groups = [];
foreach (SniffMap::RULES as $sniff => $codes) {
    $groups[implode('+', $codes)][] = $sniff;
}

$expected = [];
$sources = [];
foreach ($phpcs['files'] as $path => $file) {
    $relative = str_replace($root, '', $path);
    foreach ($file['messages'] as $message) {
        $parts = explode('.', $message['source']);
        $sniff = implode('.', array_slice($parts, 0, 3));
        if (isset($partial[$sniff]) && !in_array($parts[3] ?? '', $partial[$sniff], true)) {
            continue;
        }

        foreach ($groups as $group => $sniffs) {
            if (in_array($sniff, $sniffs, true)) {
                $expected[$group]["{$relative}:{$message['line']}"] = true;
                $sources[$group]["{$relative}:{$message['line']}"][$message['source']] = true;
            }
        }
    }
}

$actual = [];
foreach ($mago['issues'] as $issue) {
    $span = $issue['annotations'][0]['span'] ?? null;
    if ($span === null) {
        continue;
    }

    $key = str_replace($root, '', $span['file_id']['path']) . ':' . ($span['start']['line'] + 1);
    foreach (array_keys($groups) as $group) {
        if (in_array($issue['code'], explode('+', $group), true)) {
            $actual[$group][$key] = true;
        }
    }
}

$differences = 0;
$lines = 0;
foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $group) {
    $want = $expected[$group] ?? [];
    $have = $actual[$group] ?? [];
    $lines += count($want);
    $missing = array_keys(array_diff_key($want, $have));
    $extra = array_keys(array_diff_key($have, $want));
    if ($missing === [] && $extra === []) {
        continue;
    }

    $differences += count($missing) + count($extra);
    printf("%s: %d phpcs lines, %d here, %d only phpcs, %d only here\n", $group, count($want), count($have), count($missing), count($extra));
    $codes = [];
    foreach ($missing as $key) {
        foreach (array_keys($sources[$group][$key]) as $source) {
            $codes[$source] = ($codes[$source] ?? 0) + 1;
        }
    }

    arsort($codes);
    foreach (array_slice($codes, 0, 6, true) as $source => $count) {
        echo "  only phpcs, by code: {$count} {$source}\n";
    }

    foreach (array_slice($missing, 0, getenv("PARITY_ALL") ? null : 5) as $key) {
        echo "  only phpcs: {$key}\n";
    }

    foreach (array_slice($extra, 0, getenv("PARITY_ALL") ? null : 5) as $key) {
        echo "  only here:  {$key}\n";
    }
}

printf("%d lines compared, %d differences\n", $lines, $differences);
exit($differences === 0 ? 0 : 1);
