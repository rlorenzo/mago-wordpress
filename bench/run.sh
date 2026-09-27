#!/usr/bin/env bash
# Benchmarks phpcs (WordPress-Extra) against mago + this extension on a WordPress codebase.
#
#   bench/run.sh <project-dir> [<text-domain>] [<prefix>]
#
# Needs: vendor/bin/mago (composer install), php, perl, and a phpcs with WPCS on PATH or
# in $PHPCS (defaults to vendor/bin/phpcs of this package if installed). Results print as
# a markdown table; paste them into README.md.
#
# Not an apples-to-apples rule comparison: phpcs runs the full WordPress-Extra ruleset
# (WPCS 3.4.1), while mago only runs this extension's rules.
set -euo pipefail

[[ $# -ge 1 ]] || { echo "usage: bench/run.sh <project-dir> [<text-domain>] [<prefix>]" >&2; exit 1; }

project=$(cd "$1" && pwd -P)
domain=${2:-}
prefix=${3:-}
here=$(cd "$(dirname "$0")/.." && pwd -P)
mago=${MAGO:-$here/vendor/bin/mago}
phpcs=${PHPCS:-$here/vendor/bin/phpcs}

[[ -x "$mago" ]] || { echo "mago not found or not executable: $mago" >&2; exit 1; }
[[ -x "$phpcs" ]] || { echo "phpcs not found or not executable: $phpcs" >&2; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# PHP writes all three configs so every value is escaped by a real encoder. A JSON string
# with unescaped slashes is also a valid TOML basic string.
# shellcheck disable=SC2016 # the single-quoted $ are PHP variables, not shell ones
php -r '
[, $project, $worker, $domain, $prefix, $work] = $argv;
$json = fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$xml = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1);

file_put_contents("$work/mago.toml", <<<TOML
php-version = "8.2"
[source]
paths = [{$json($project)}]
excludes = ["**/vendor/**", "**/vendor_prefixed/**", "**/node_modules/**", "**/tests/**"]
[linter]
integrations = ["wordpress"]
[extension-hosts.wordpress]
command = ["php", {$json($worker)}]

TOML);

file_put_contents("$work/composer.json", json_encode(
    ["extra" => ["mago-wordpress" => ["text-domains" => [$domain], "prefixes" => [$prefix]]]],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
) . "\n");

file_put_contents("$work/phpcs.xml", <<<XML
<?xml version="1.0"?>
<ruleset name="bench">
  <rule ref="WordPress-Extra"/>
  <rule ref="WordPress.WP.I18n"><properties><property name="text_domain" type="array"><element value="{$xml($domain)}"/></property></properties></rule>
  <rule ref="WordPress.NamingConventions.PrefixAllGlobals"><properties><property name="prefixes" type="array"><element value="{$xml($prefix)}"/></property></properties></rule>
</ruleset>

XML);
' "$project" "$here/resources/worker.php" "$domain" "$prefix" "$work"

codes=$(paste -sd, - < "$here/tests/corpus/expected-rules.txt")

# The worker discovers composer.json via getcwd(), so mago must run from $work.
run_mago() { (cd "$work" && "$mago" --config "$work/mago.toml" lint --only "$codes" --reporting-format code-count); }
run_phpcs() {
    "$phpcs" --standard="$work/phpcs.xml" --extensions=php \
        --ignore='*/vendor/*,*/vendor_prefixed/*,*/node_modules/*,*/tests/*' \
        --parallel=8 -d memory_limit=2G --report=summary "$project"
}

# A linter that finds issues exits 1 (phpcs: 2 when some are auto-fixable); only those or 0
# (clean) are acceptable. But phpcs also exits 2 on some runtime errors, and mago's worker can
# crash mid-run while mago itself still exits its "found issues" code (1) — exit status alone
# doesn't prove the run was clean, so callers also pass a crash pattern to grep for in the
# output. That grep is a heuristic (mago's/phpcs's exact wording isn't a stable contract), not
# a real parse of either tool's report.
# Output goes to a file rather than a captured variable, so timed runs pay no subshell.
run_checked() { # name, function, crash-pattern (grep -E, optional)
    local name=$1 fn=$2 crash=${3:-} status=0
    "$fn" > "$work/out" 2>&1 || status=$?
    if (( status > 2 )) || { [[ -n "$crash" ]] && grep -qiE "$crash" "$work/out"; }; then
        echo "error: $name exited with status $status:" >&2
        cat "$work/out" >&2
        exit 1
    fi
}
mago_crash='extension host|worker (crashed|panicked|failed|exited unexpectedly)'
phpcs_crash='PHP Fatal error|Uncaught (Error|Exception)'

files=$(find "$project" -name '*.php' -not -path '*/vendor/*' -not -path '*/vendor_prefixed/*' -not -path '*/node_modules/*' -not -path '*/tests/*' | wc -l | tr -d ' ')
mago_version=$("$mago" --version 2>&1 | tail -1)
phpcs_version=$("$phpcs" --version 2>&1 | tail -1)

echo "## $(basename "$project") ($files PHP files)"
echo
echo "$mago_version / $phpcs_version"
echo
echo "mago issue counts by rule:"
run_checked mago run_mago "$mago_crash"
cat "$work/out"
echo

# Mean of 3 timed runs after 1 warm-up (hyperfine hangs on mago's worker protocol). Each
# run is re-checked so a crash mid-benchmark fails the script instead of skewing the mean.
timed() { # name, function, crash-pattern
    local name=$1 fn=$2 crash=$3 total=0 t0 t1
    run_checked "$name" "$fn" "$crash"
    for _ in 1 2 3; do
        t0=$(perl -MTime::HiRes=time -e 'printf "%.3f", time')
        run_checked "$name" "$fn" "$crash"
        t1=$(perl -MTime::HiRes=time -e 'printf "%.3f", time')
        total=$(perl -e "print $total + ($t1 - $t0)")
    done
    perl -e "printf \"| %s | %.2f s |\n\", '$name', $total / 3"
}
echo "| Tool | Mean of 3 runs |"
echo "|:---|---:|"
timed "phpcs WordPress-Extra (WPCS 3.4.1, --parallel=8)" run_phpcs "$phpcs_crash"
timed "mago + mago-wordpress" run_mago "$mago_crash"
