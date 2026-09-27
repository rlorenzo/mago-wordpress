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

# Starts a real worker and checks the inline `@mago-expect` annotations in tests/corpus/src.
# The first step pins the registered rule codes to tests/corpus/expected-rules.txt, because
# `--only` silently drops expectations for a rule that lost its registration.
test-corpus:
    {{mago}} --workspace tests/corpus extension list --json | php -r '$registered = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); $actual = []; foreach ($registered["extensions"] as $extension) { foreach ($extension["linter-rules"] as $rule) { $actual[] = $rule["code"]; } } sort($actual); $expected = array_map("trim", file("tests/corpus/expected-rules.txt")); sort($expected); if ($actual === $expected) { exit(0); } fwrite(STDERR, "Registered rules drifted from tests/corpus/expected-rules.txt\n"); foreach (array_diff($expected, $actual) as $code) { fwrite(STDERR, "  no longer registered: $code\n"); } foreach (array_diff($actual, $expected) as $code) { fwrite(STDERR, "  not pinned: $code\n"); } exit(1);'
    {{mago}} --workspace tests/corpus lint --only "$(paste -sd, - < tests/corpus/expected-rules.txt)"

check: validate format-check test lint analyze test-corpus
