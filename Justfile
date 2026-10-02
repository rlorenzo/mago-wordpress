set dotenv-load := false

# Set MAGO=/path/to/mago to run the checks against a different binary.
mago := env_var_or_default("MAGO", "vendor/bin/mago")

validate:
    composer validate --strict --no-check-publish

test:
    vendor/bin/phpunit --configuration phpunit.xml

lint:
    {{mago}} --config mago.toml lint

analyze:
    {{mago}} --config mago.toml analyze

format:
    {{mago}} --config mago.toml format

format-check:
    {{mago}} --config mago.toml format --check

# Starts a real worker and checks the inline `@mago-expect` annotations under tests/corpus/rules.
# Each rule is linted alone (`--only`) against its own fixture directory, so a rule with global
# reach cannot fire on another rule's fixtures. The first step pins the registered rule codes to
# tests/corpus/expected-rules.txt, because `--only` silently drops expectations for a rule that
# lost its registration; the corpus mago.toml sets minimum-fail-level = "note" so any report
# without a matching expectation fails the run.
test-corpus:
    {{mago}} --workspace tests/corpus extension list --json | php tests/check-registered-rules.php
    for code in $(cat tests/corpus/expected-rules.txt); do dir="rules/${code#wordpress/}"; test -d "tests/corpus/$dir" || { echo "missing fixture directory tests/corpus/$dir" >&2; exit 1; }; {{mago}} --workspace tests/corpus lint --only "$code" "$dir" || exit 1; done

# wordpress.mago.toml's core rule switches and the wordpress-core/-extra presets must be what
# bin/generate-presets.php derives from SniffMap and the installed Mago's default rules.
presets-check:
    php bin/generate-presets.php --check

# Lints the whole corpus with every rule at once and checks only that the worker survives: the
# per-rule `--only` runs above cannot catch rules breaking each other (two tokenizers sharing a
# file-cache key once crashed the worker in real use). Findings are expected here (exit 1); a
# crash exits 2 or prints a worker/extension-host error.
test-corpus-smoke:
    #!/usr/bin/env bash
    set -uo pipefail
    out=$({{mago}} --workspace tests/corpus lint --only "$(paste -sd, tests/corpus/expected-rules.txt)" --reporting-format count 2>&1)
    status=$?
    if (( status > 1 )) || grep -qiE 'extension host|worker (crashed|panicked|failed|exited)' <<< "$out"; then
        echo "$out" >&2
        echo "corpus smoke run: the worker crashed (exit $status)" >&2
        exit 1
    fi
    echo "corpus smoke run: no crash (exit $status)"

check: validate format-check presets-check test lint analyze test-corpus test-corpus-smoke
