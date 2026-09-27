<?php

/**
 * Compares the rule codes in `mago extension list --json` (read from stdin) with
 * tests/corpus/expected-rules.txt, because `lint --only` silently drops expectations
 * for a rule that lost its registration.
 */

declare(strict_types=1);

(static function (): void {
    $registered = json_decode((string) stream_get_contents(STDIN), associative: true, flags: JSON_THROW_ON_ERROR);

    $actual = [];
    foreach ($registered['extensions'] ?? [] as $extension) {
        foreach ($extension['linter-rules'] ?? [] as $rule) {
            $actual[] = (string) $rule['code'];
        }
    }

    $expected = file(__DIR__ . '/corpus/expected-rules.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    sort($actual);
    sort($expected);
    if ($actual === $expected) {
        return;
    }

    fwrite(STDERR, data: "Registered rules drifted from tests/corpus/expected-rules.txt\n");
    foreach (array_diff($expected, $actual) as $code) {
        fwrite(STDERR, data: "  no longer registered: {$code}\n");
    }

    foreach (array_diff($actual, $expected) as $code) {
        fwrite(STDERR, data: "  not pinned: {$code}\n");
    }

    exit(1);
})();
